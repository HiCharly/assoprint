<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForcedPasswordChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Remplacement obligatoire d'un mot de passe temporaire généré par un
 * administrateur (voir Admin\UserController::resetPassword).
 */
class ForcedPasswordChangeController extends Controller
{
    /**
     * Show the forced password change form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('auth/forced-password-change', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Replace the temporary password with one chosen by the user.
     */
    public function update(ForcedPasswordChangeRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password' => $request->string('password')->value(),
            'must_change_password' => false,
        ])->save();

        // Le mot de passe temporaire a circulé hors de l'application : toute
        // autre session ouverte avec lui doit tomber.
        Auth::logoutOtherDevices($request->string('password')->value());

        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Votre mot de passe a été mis à jour.')]);

        return to_route('dashboard');
    }
}
