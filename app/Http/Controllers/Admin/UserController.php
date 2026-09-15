<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use App\Enums\PrintJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Print\RelaunchPrintJobRequest;
use App\Http\Resources\PrintJobResource;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\CupsPrintService;
use App\Services\PrintJobSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly PrintJobSubmissionService $submissions,
        private readonly CupsPrintService $cups,
    ) {}

    /**
     * List every account with its cumulated page count.
     */
    public function index(Request $request): Response
    {
        $users = User::query()
            ->withSum(
                ['printJobs as pages_printed_total' => fn ($query) => $query
                    ->where('status', PrintJobStatus::Printed)
                    ->where('counts_pages', true)],
                'pages_printed',
            )
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'login' => $user->login,
                'is_admin' => $user->is_admin,
                'is_active' => $user->is_active,
                'must_change_password' => $user->must_change_password,
                'pages_printed' => (int) ($user->pages_printed_total ?? 0),
            ]);

        return Inertia::render('admin/users', [
            'users' => $users,
        ]);
    }

    /**
     * Show the account creation form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/user-create');
    }

    /**
     * Create an account with a temporary password shown once.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $password = Str::password(12);

        $user = new User([
            'name' => $request->string('name')->value(),
            'login' => $request->string('login')->value(),
            'password' => $password,
        ]);

        $user->forceFill([
            'is_admin' => $request->boolean('is_admin'),
            'must_change_password' => true,
        ])->save();

        $this->audit('création du compte', $request->user(), $user);

        // Le mot de passe ne transite que par le flash de session, le temps
        // d'une redirection, et n'est donc affiché qu'une seule fois.
        return to_route('admin.users.index')
            ->with('temporaryPassword', ['name' => $user->name, 'password' => $password]);
    }

    /**
     * Show the account edition form.
     */
    public function edit(Request $request, User $user): Response
    {
        return Inertia::render('admin/user-edit', [
            'user' => $this->present($user),
        ]);
    }

    /**
     * Update the name, login and role of an account.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $isAdmin = $request->boolean('is_admin');

        // Un administrateur ne peut pas se retirer ses propres droits : il se
        // fermerait la porte du back-office, potentiellement sans personne
        // d'autre pour la rouvrir.
        if ($user->is($request->user()) && ! $isAdmin) {
            return back()->withErrors([
                'is_admin' => 'Vous ne pouvez pas retirer vos propres droits administrateur.',
            ]);
        }

        $user->fill([
            'name' => $request->string('name')->value(),
            'login' => $request->string('login')->value(),
        ]);

        $user->forceFill(['is_admin' => $isAdmin])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Compte mis à jour.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * Show one account, its counter and its print jobs.
     */
    public function show(Request $request, User $user): Response
    {
        $jobs = $user->printJobs()->latest()->limit(100)->get();

        return Inertia::render('admin/user-show', [
            'user' => $this->present($user),
            'pagesPrinted' => $user->pagesPrinted(),
            'jobs' => PrintJobResource::collection($jobs),
        ]);
    }

    /**
     * Activate or deactivate an account.
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        // Se désactiver soi-même reviendrait à se déconnecter définitivement :
        // le middleware « active » ferme la session à la requête suivante.
        if ($user->is($request->user())) {
            return back()->withErrors([
                'is_active' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        $this->audit(
            $user->is_active ? 'activation du compte' : 'désactivation du compte',
            $request->user(),
            $user,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->is_active ? __('Compte activé.') : __('Compte désactivé.'),
        ]);

        return back();
    }

    /**
     * Give the account a new temporary password, shown once.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $password = Str::password(12);

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        $this->audit('réinitialisation du mot de passe', $request->user(), $user);

        return back()->with('temporaryPassword', [
            'name' => $user->name,
            'password' => $password,
        ]);
    }

    /**
     * Show the relaunch form of one of the member's jobs.
     */
    public function relaunchCreate(Request $request, User $user, PrintJob $printJob): Response
    {
        return Inertia::render('admin/relaunch', [
            'user' => $this->present($user),
            'job' => new PrintJobResource($printJob),
            'options' => $this->printOptions(),
        ]);
    }

    /**
     * Reprint one of the member's documents, as a fix.
     */
    public function relaunch(RelaunchPrintJobRequest $request, User $user, PrintJob $printJob): RedirectResponse
    {
        if (! $printJob->fileExists()) {
            return back()->withErrors([
                'file' => 'Le fichier de cette tâche n’est plus disponible sur le serveur.',
            ]);
        }

        // Dépannage : les pages ne sont pas portées au compteur du membre. Le
        // document n'est pas sorti la première fois, il n'a donc rien à payer
        // deux fois.
        $this->submissions->resubmit(
            $printJob,
            (int) $request->integer('copies'),
            Duplex::from((string) $request->string('duplex')),
            ColorMode::from((string) $request->string('color_mode')),
            countsPages: false,
        );

        $this->audit("relance de la tâche #{$printJob->id}", $request->user(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document renvoyé à l’imprimante.')]);

        return to_route('admin.users.show', $user);
    }

    /**
     * The choices offered by the relaunch form.
     *
     * @return array<string, mixed>
     */
    private function printOptions(): array
    {
        return [
            'duplex' => array_map(
                fn (Duplex $case) => ['value' => $case->value, 'label' => $case->label()],
                Duplex::cases(),
            ),
            'colorModes' => array_map(
                fn (ColorMode $case) => ['value' => $case->value, 'label' => $case->label()],
                ColorMode::cases(),
            ),
            'maxCopies' => (int) config('print.max_copies'),
            'maxFileSizeMb' => (int) round(((int) config('print.max_file_size_kb')) / 1024),
            // Un dépannage vise souvent un document qui n'est pas sorti : savoir
            // que l'imprimante est encore bloquée évite à l'administrateur de
            // relancer dans le vide.
            'printerNotice' => $this->cups->depositNotice(),
        ];
    }

    /**
     * The account as shown by the back-office.
     *
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'login' => $user->login,
            'is_admin' => $user->is_admin,
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
        ];
    }

    /**
     * Record a sensitive administrative action.
     *
     * Le mot de passe temporaire n’apparaît évidemment jamais ici : seul le
     * fait qu’une réinitialisation a eu lieu est journalisé.
     */
    private function audit(string $action, ?User $admin, User $target): void
    {
        Log::info('Action administrateur : '.$action, [
            'admin_id' => $admin?->id,
            'user_id' => $target->id,
        ]);
    }
}
