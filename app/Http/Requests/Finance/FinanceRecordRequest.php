<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Reglas compartidas por los formularios de finanzas.
 *
 * La autorización (¿el registro es tuyo?) la decide FinanceRecordPolicy en el
 * controlador; aquí solo se valida la forma de los datos.
 */
abstract class FinanceRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function descriptionRules(): array
    {
        return ['required', 'string', 'max:100'];
    }

    protected function amountRules(): array
    {
        return ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'];
    }

    /** Solo categorías activas del usuario autenticado. */
    protected function activeCategoryRules(): array
    {
        return ['required', 'integer', $this->ownActiveCategory()];
    }

    private function ownActiveCategory(): Exists
    {
        return Rule::exists('categoria', 'id')
            ->where('user_id', $this->user()->id)
            ->where('estado', true);
    }
}
