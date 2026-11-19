<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use BelongsToUser;

    protected $table = 'categoria';

    protected $fillable = ['description'];

    /** @return HasMany<Expense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'id_category');
    }

    /** @return HasMany<Budget, $this> */
    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class, 'id_category');
    }

    /** Una categoría con gastos o presupuestos no se puede desactivar. */
    public function isInUse(): bool
    {
        return $this->expenses()->exists() || $this->budgets()->exists();
    }
}
