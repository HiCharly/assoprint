<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte immédiatement un membre dont le compte a été désactivé.
 *
 * La désactivation doit prendre effet tout de suite, y compris sur une session
 * déjà ouverte : sans cela, un membre exclu du club continuerait d'imprimer
 * jusqu'à l'expiration de son cookie de session.
 */
class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Ce compte a été désactivé. Contactez un administrateur.'),
            ]);
        }

        return $next($request);
    }
}
