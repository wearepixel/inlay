<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property string $handle
 * @property int $order
 * @property array<string, mixed> $settings
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Page $page
 */
final class PageSection extends Model
{
    protected $fillable = ['page_id', 'handle', 'order', 'settings'];

    protected $casts = [
        'order' => 'integer',
        'settings' => 'array',
    ];

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
