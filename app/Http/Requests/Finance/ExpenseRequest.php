<?php

namespace App\Http\Requests\Finance;

class ExpenseRequest extends FinanceRecordRequest
{
    public function rules(): array
    {
        return [
            'description' => $this->descriptionRules(),
            'amount' => $this->amountRules(),
            'date' => ['required', 'date_format:Y-m-d'],
            'save' => ['required', 'boolean'],
            'id_category' => $this->activeCategoryRules(),
        ];
    }
}
