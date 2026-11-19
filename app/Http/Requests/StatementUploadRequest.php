<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatementUploadRequest extends FormRequest
{
    public const MAX_KILOBYTES = 1024;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'mimes:csv,txt', 'max:'.self::MAX_KILOBYTES],
            // Categoría para los gastos del extracto: una propia y activa.
            'id_category' => [
                'required',
                'integer',
                Rule::exists('categoria', 'id')
                    ->where('user_id', $this->user()->id)
                    ->where('estado', true),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'archivo', 'id_category' => 'categoría'];
    }

    /**
     * Contenido del archivo en UTF-8. Excel en Windows suele exportar CSV en
     * Windows-1252: sin esta conversión, las tildes se romperían y PostgreSQL
     * rechazaría el texto.
     */
    public function utf8Content(): string
    {
        $content = $this->file('file')->get();

        return mb_check_encoding($content, 'UTF-8')
            ? $content
            : mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
    }
}
