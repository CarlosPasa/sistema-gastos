<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Un usuario solo puede ver y modificar sus propios registros financieros.
 *
 * Se responde 404 (no 403) cuando el registro es de otra persona: así no se
 * revela que ese ID existe.
 */
class FinanceRecordPolicy
{
    public function view(User $user, Category|Expense|Income|Budget $record): Response
    {
        return $this->ownership($user, $record);
    }

    public function update(User $user, Category|Expense|Income|Budget $record): Response
    {
        return $this->ownership($user, $record);
    }

    public function delete(User $user, Category|Expense|Income|Budget $record): Response
    {
        return $this->ownership($user, $record);
    }

    public function restore(User $user, Category|Expense|Income|Budget $record): Response
    {
        return $this->ownership($user, $record);
    }

    private function ownership(User $user, Category|Expense|Income|Budget $record): Response
    {
        return $record->isOwnedBy($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
