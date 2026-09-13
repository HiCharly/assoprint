<?php

namespace App\Http\Requests\Auth;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForcedPasswordChangeRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * Le mot de passe temporaire n'est pas redemandé : l'utilisateur vient de
     * s'en servir pour se connecter, et le réclamer ne protégerait de rien.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => $this->passwordRules(),
        ];
    }
}
