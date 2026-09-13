<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force le remplacement d'un mot de passe temporaire avant toute autre action.
 *
 * Un mot de passe généré par l'administrateur transite forcément hors de
 * l'application (oral, SMS…) : il est considéré comme compromis dès qu'il est
 * communiqué, et ne doit donc servir qu'une fois.
 */
class EnsureUserHasChosenPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && $user->must_change_password
            && ! $request->routeIs('password.change', 'password.change.update', 'logout')
        ) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
