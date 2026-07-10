<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WearePixel\BladePageBuilder\Enums\PageStatus;

final class Page extends Model
{
    protected $fillable = ['title', 'slug', 'status', 'meta_title', 'meta_description'];

    protected $casts = [
        'status' => PageStatus::class,
    ];

    protected $attributes = [
        'status' => PageStatus::Draft->value,
    ];

    public function sections(): HasMany
    {
        return $this->hasMany(PageSection::class)->orderBy('order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest();
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }
}
