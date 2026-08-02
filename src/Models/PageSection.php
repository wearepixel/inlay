<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PageSection extends Model
{
    protected $fillable = ['page_id', 'handle', 'order', 'settings'];

    protected $casts = [
        'order' => 'integer',
        'settings' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
