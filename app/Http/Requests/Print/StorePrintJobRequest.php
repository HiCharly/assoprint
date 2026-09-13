<?php

namespace App\Http\Requests\Print;

use App\Enums\ColorMode;
use App\Enums\Duplex;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrintJobRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * `mimes:pdf` comme `mimetypes:application/pdf` s'appuient sur le contenu
     * réel du fichier (via finfo), pas sur son extension : renommer un exécutable
     * en .pdf ne suffit donc pas à le faire accepter.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:'.(int) config('print.max_file_size_kb'),
            ],
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
            'file.required' => 'Choisissez un fichier PDF à imprimer.',
            'file.mimes' => 'Seuls les fichiers PDF peuvent être imprimés.',
            'file.mimetypes' => 'Seuls les fichiers PDF peuvent être imprimés.',
            'file.max' => 'Le fichier dépasse la taille maximale autorisée ('.(int) config('print.max_file_size_kb') / 1024 .' Mo).',
            'copies.max' => 'Le nombre de copies est limité à :max par impression.',
        ];
    }
}
