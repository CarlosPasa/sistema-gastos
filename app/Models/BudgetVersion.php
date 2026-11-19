<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Límite de un presupuesto vigente desde `effective_month` (primer día del
 * mes) hasta la siguiente versión.
 */
class BudgetVersion extends Model
{
    protected $table = 'presupuesto_vigencia';

    protected $fillable = ['amount', 'effective_month'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'presupuesto_id');
    }
}
