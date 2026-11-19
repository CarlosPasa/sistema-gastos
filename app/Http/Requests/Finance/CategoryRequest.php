<?php

namespace App\Http\Requests\Finance;

class CategoryRequest extends FinanceRecordRequest
{
    public function rules(): array
    {
        return [
            'description' => $this->descriptionRules(),
        ];
    }
}
