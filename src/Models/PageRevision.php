<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PageRevision extends Model
{
    protected $fillable = ['page_id', 'snapshot', 'created_by', 'label'];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
