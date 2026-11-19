<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un extracto subido por el usuario y el resultado de procesarlo.
 */
class StatementImport extends Model
{
    protected $fillable = ['original_name', 'content', 'category_id'];

    protected $hidden = ['content'];

    protected $casts = [
        'status' => ImportStatus::class,
        'row_errors' => 'array',
        'processed_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
