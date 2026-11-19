<?php

namespace App\Http\Requests\Finance;

class IncomeRequest extends FinanceRecordRequest
{
    public function rules(): array
    {
        return [
            'description' => $this->descriptionRules(),
            'amount' => $this->amountRules(),
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
