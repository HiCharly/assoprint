<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->normalisedLogin($this->input('login')));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Le mot de passe n'est pas saisi par l'administrateur : il est tiré au
     * hasard puis affiché une seule fois (voir UserController::store).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            // Une case décochée n'est tout simplement pas envoyée par le
            // navigateur : l'absence vaut « non administrateur ».
            'is_admin' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->loginMessages();
    }
}
