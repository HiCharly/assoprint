<?php

namespace App\Http\Controllers;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use App\Http\Requests\Print\DuplicatePrintJobRequest;
use App\Http\Requests\Print\StorePrintJobRequest;
use App\Http\Resources\PrintJobResource;
use App\Models\PrintJob;
use App\Services\PrintJobSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrintJobController extends Controller
{
    public function __construct(private readonly PrintJobSubmissionService $submissions) {}

    /**
     * Show the deposit form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('print/create', [
            'options' => $this->options(),
        ]);
    }

    /**
     * Accept a PDF and queue it for printing.
     */
    public function store(StorePrintJobRequest $request): RedirectResponse
    {
        $this->submissions->submitUpload(
            $request->user(),
            $request->file('file'),
            (int) $request->integer('copies'),
            Duplex::from((string) $request->string('duplex')),
            ColorMode::from((string) $request->string('color_mode')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document envoyé à l’imprimante.')]);

        return to_route('print.jobs');
    }

    /**
     * List the jobs of the authenticated member.
     */
    public function index(Request $request): Response
    {
        $jobs = $request->user()->printJobs()
            ->latest()
            ->limit(100)
            ->get();

        return Inertia::render('print/jobs', [
            'jobs' => PrintJobResource::collection($jobs),
            'pagesPrinted' => $request->user()->pagesPrinted(),
        ]);
    }

    /**
     * Show the form to submit an existing job again.
     */
    public function duplicateCreate(Request $request, PrintJob $printJob): Response
    {
        $this->authorize('duplicate', $printJob);

        return Inertia::render('print/duplicate', [
            'job' => new PrintJobResource($printJob),
            'options' => $this->options(),
        ]);
    }

    /**
     * Queue an already deposited PDF again.
     */
    public function duplicate(DuplicatePrintJobRequest $request, PrintJob $printJob): RedirectResponse
    {
        $this->authorize('duplicate', $printJob);

        if (! $printJob->fileExists()) {
            return back()->withErrors([
                'file' => "Le fichier de cette tâche n'est plus disponible sur le serveur.",
            ]);
        }

        $this->submissions->resubmit(
            $printJob,
            (int) $request->integer('copies'),
            Duplex::from((string) $request->string('duplex')),
            ColorMode::from((string) $request->string('color_mode')),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Document renvoyé à l’imprimante.')]);

        return to_route('print.jobs');
    }

    /**
     * The choices offered by the deposit and duplication forms.
     *
     * @return array<string, mixed>
     */
    private function options(): array
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
        ];
    }
}
