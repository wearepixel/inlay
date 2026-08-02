<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WeArePixel\Inlay\Enums\PageStatus;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property PageStatus $status
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PageSection> $sections
 * @property-read Collection<int, PageRevision> $revisions
 */
final class Page extends Model
{
    protected $fillable = ['title', 'slug', 'status', 'meta_title', 'meta_description'];

    protected $casts = [
        'status' => PageStatus::class,
    ];

    protected $attributes = [
        'status' => PageStatus::Draft->value,
    ];

    /**
     * @return HasMany<PageSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('order');
    }

    /**
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest();
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }
}
