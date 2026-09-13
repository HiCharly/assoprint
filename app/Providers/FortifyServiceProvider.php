<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureAuthentication();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure how credentials are verified.
     *
     * Un compte désactivé est refusé dès la connexion, avec le message d'erreur
     * générique : distinguer « compte désactivé » de « identifiants invalides »
     * à ce stade révélerait l'existence de l'adresse à un inconnu.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::where('email', (string) $request->input(Fortify::username()))->first();

            if ($user === null || ! $user->is_active) {
                return null;
            }

            return Hash::check((string) $request->input('password'), $user->password)
                ? $user
                : null;
        });
    }

    /**
     * Configure Fortify views.
     *
     * Seule la vue de connexion est enregistrée : inscription, vérification
     * d'email et réinitialisation par email sont désactivées (config/fortify.php).
     * Un mot de passe oublié passe par un administrateur, voir docs/security.md.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
