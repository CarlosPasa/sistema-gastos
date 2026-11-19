<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    use BelongsToUser;

    protected $table = 'ingreso';

    protected $fillable = ['description', 'amount'];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Los ingresos aún no tienen fecha propia: el periodo se calcula con
     * created_at. Rango semiabierto [desde, hasta).
     */
    public function scopeInPeriod(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->where($query->qualifyColumn('created_at'), '>=', $from)
            ->where($query->qualifyColumn('created_at'), '<', $until);
    }
}
