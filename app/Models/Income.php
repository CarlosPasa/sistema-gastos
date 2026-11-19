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

    protected $fillable = ['description', 'amount', 'date'];

    // `date` se deja como texto (Y-m-d): así lo esperan los formularios.
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /** Rango semiabierto [desde, hasta) sobre la fecha del ingreso. */
    public function scopeInPeriod(Builder $query, CarbonInterface $from, CarbonInterface $until): void
    {
        $query->where($query->qualifyColumn('date'), '>=', $from->toDateString())
            ->where($query->qualifyColumn('date'), '<', $until->toDateString());
    }
}
