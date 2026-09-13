<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            // Une case décochée n'est tout simplement pas envoyée par le
            // navigateur : l'absence vaut « non administrateur ».
            'is_admin' => ['nullable', 'boolean'],
        ];
    }
}
