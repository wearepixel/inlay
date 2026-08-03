<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property array{sections: array<int, array{id: int, handle: string, order: int, settings: array<string, mixed>}>, title: string, meta_title: string|null, meta_description: string|null, definition: string|null} $snapshot
 * @property int|null $created_by
 * @property string|null $label
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Page $page
 */
final class PageRevision extends Model
{
    protected $fillable = ['page_id', 'snapshot', 'created_by', 'label'];

    protected $casts = [
        'snapshot' => 'array',
    ];

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
