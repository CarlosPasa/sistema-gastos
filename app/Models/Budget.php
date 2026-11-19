<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Presupuesto mensual recurrente por categoría. `amount` guarda el límite
 * vigente más reciente; el historial por mes vive en BudgetVersion.
 */
class Budget extends Model
{
    use BelongsToUser;

    protected $table = 'presupuesto';

    protected $fillable = ['description', 'amount', 'id_category'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'id_category');
    }

    /** @return HasMany<BudgetVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class, 'presupuesto_id');
    }
}
