<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'login' => $this->loginRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate logins.
     *
     * Le jeu de caractères est volontairement étroit : un identifiant se dicte
     * au téléphone et se tape sans hésiter. Les accents, espaces et majuscules
     * n'apporteraient que des connexions ratées.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function loginRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:50',
            'regex:/^[a-z0-9._-]+$/',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }

    /**
     * Normalise the submitted login before it is validated.
     *
     * @return array<string, string>
     */
    protected function normalisedLogin(mixed $login): array
    {
        return is_string($login)
            ? ['login' => mb_strtolower(trim($login))]
            : [];
    }

    /**
     * Get the validation messages for the login field.
     *
     * @return array<string, string>
     */
    protected function loginMessages(): array
    {
        return [
            'login.regex' => 'L’identifiant ne peut contenir que des lettres sans accent, des chiffres, un point, un tiret ou un tiret bas.',
            'login.unique' => 'Cet identifiant est déjà utilisé.',
            'login.min' => 'L’identifiant doit faire au moins :min caractères.',
        ];
    }
}
