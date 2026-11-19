<?php

namespace App\Http\Requests\Finance;

class BudgetRequest extends FinanceRecordRequest
{
    public function rules(): array
    {
        return [
            'description' => $this->descriptionRules(),
            'amount' => $this->amountRules(),
            'id_category' => $this->activeCategoryRules(),
            // Mes desde el que rige el límite (Y-m). Ver BudgetRecurrenceService.
            'effective_month' => ['required', 'date_format:Y-m'],
        ];
    }
}
