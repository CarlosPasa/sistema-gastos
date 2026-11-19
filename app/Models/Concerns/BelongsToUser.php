<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comportamiento común de los registros financieros: pertenecen a un usuario
 * y se desactivan (columna `estado`) en lugar de borrarse.
 *
 * `user_id` y `estado` no son asignables en masa: el dueño se fija al crear
 * desde la relación ($user->expenses()->create(...)) y el estado solo cambia
 * con activate() / deactivate().
 */
trait BelongsToUser
{
    public function initializeBelongsToUser(): void
    {
        $this->mergeCasts(['estado' => 'boolean']);
        $this->attributes['estado'] ??= true;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('estado'), true);
    }

    /**
     * Búsqueda por descripción sin distinguir mayúsculas. Los comodines de
     * LIKE (% y _) que escriba el usuario se tratan como texto literal.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($term, 0, 100));

        $query->where($query->qualifyColumn('description'), 'ilike', "%{$escaped}%");
    }

    public function deactivate(): void
    {
        $this->forceFill(['estado' => false])->save();
    }

    public function activate(): void
    {
        $this->forceFill(['estado' => true])->save();
    }
}
