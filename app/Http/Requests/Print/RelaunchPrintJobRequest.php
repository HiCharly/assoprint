<?php

namespace App\Http\Requests\Print;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RelaunchPrintJobRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Mêmes règles qu'une duplication : une relance administrateur n'accorde
     * aucun privilège supplémentaire sur les réglages d'impression.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'copies' => ['required', 'integer', 'min:1', 'max:'.(int) config('print.max_copies')],
            'duplex' => ['required', Rule::enum(Duplex::class)],
            'color_mode' => ['required', Rule::enum(ColorMode::class)],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'copies.max' => 'Le nombre de copies est limité à :max par impression.',
        ];
    }
}
