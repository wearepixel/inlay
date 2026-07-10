<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PageSection extends Model
{
    protected $fillable = ['page_id', 'section_type', 'order', 'props'];

    protected $casts = [
        'order' => 'integer',
        'props' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
