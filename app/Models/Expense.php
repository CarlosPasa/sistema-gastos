<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento de salida de dinero. Con save = true es un ahorro;
 * con save = false es un gasto de consumo.
 */
class Expense extends Model
{
    use BelongsToUser;

    protected $table = 'gasto';

    protected $fillable = ['description', 'amount', 'date', 'save', 'id_category'];

    // `date` se deja como texto (Y-m-d): así lo esperan los formularios.
    protected $casts = [
        'amount' => 'decimal:2',
        'save' => 'boolean',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'id_category');
    }

    public function scopeConsumption(Builder $query): void
    {
        $query->where($query->qualifyColumn('save'), false);
    }

    public function scopeSavings(Builder $query): void
    {
        $query->where($query->qualifyColumn('save'), true);
    }

    /** Rango semiabierto [desde, hasta): incluye todo el último día del periodo. */
    public function scopeInPeriod(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->where($query->qualifyColumn('date'), '>=', $from->toDateString())
            ->where($query->qualifyColumn('date'), '<', $until->toDateString());
    }
}
