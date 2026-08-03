<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use WeArePixel\Inlay\Enums\PageStatus;

/**
 * @property int $id
 * @property string $title
 * @property string|null $path
 * @property PageStatus $status
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property bool $is_homepage
 * @property string|null $definition
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PageSection> $sections
 * @property-read Collection<int, PageRevision> $revisions
 */
final class Page extends Model
{
    protected $fillable = ['title', 'path', 'status', 'meta_title', 'meta_description', 'is_homepage', 'definition'];

    protected $casts = [
        'status' => PageStatus::class,
        'is_homepage' => 'boolean',
    ];

    protected $attributes = [
        'status' => PageStatus::Draft->value,
        'is_homepage' => false,
    ];

    /**
     * Normalises on write so every caller - page creation, a future Page
     * Settings save, a direct ->update() - gets the same canonical form for
     * free, rather than each one having to remember to call a helper first.
     * "/test/test/", "/test//test", and "test/test" all collapse to the
     * same stored value: "test/test". An all-slash or empty value becomes
     * null (no path), never an empty string - see Inlay::url()'s docblock
     * for why an empty string is never a valid "this is the homepage"
     * signal.
     */
    public function setPathAttribute(?string $value): void
    {
        $this->attributes['path'] = self::normalizePath($value);
    }

    public static function normalizePath(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value, "/ \t\n\r\0\x0B");
        $collapsed = preg_replace('#/+#', '/', $trimmed) ?? $trimmed;

        return $collapsed === '' ? null : $collapsed;
    }

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

    /**
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    public function scopeHomepage(Builder $query): Builder
    {
        return $query->where('is_homepage', true);
    }

    /**
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PageStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }

    /**
     * The live/draft title an editor is currently working on - what the
     * admin's own Page Settings panel shows. Public rendering through
     * Inlay's own routing uses the latest revision's own snapshotted title
     * instead (see PagesController), so editing this never changes what a
     * live visitor sees until the next publish - the same guarantee already
     * in place for Block settings, extended to Page-level metadata.
     */
    public function effectiveMetaTitle(): string
    {
        return $this->meta_title ?? $this->title;
    }

    public function effectiveMetaDescription(): ?string
    {
        return $this->meta_description;
    }
}
