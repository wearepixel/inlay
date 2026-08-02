<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use Illuminate\Support\Facades\Storage;
use Stringable;

/**
 * What an Image field hydrates to when a block is actually rendered -
 * never the bare disk-relative path SectionRenderer stores, so a block's
 * view never has to know which disk it was uploaded to or how to turn that
 * path into a real URL. __toString() resolves the URL directly, so the
 * naive `<img src="{{ $image }}">` a developer reaches for first is
 * correct, not merely one you have to learn requires a Storage::url() call
 * first - the mistake this type exists to make impossible.
 *
 * Deliberately never null itself - the same convention LinkValue already
 * uses. A block that wants "no image" to behave like a genuinely
 * absent value should type the parameter `?ImageValue $photo = null`
 * instead; SectionRenderer honours that nullability and only ever
 * constructs one of these when there is a real path to resolve. A
 * non-nullable `ImageValue $photo = new ImageValue()` still gets a real
 * (empty) instance - check isEmpty() rather than truthiness, since any
 * object is truthy regardless of its contents.
 */
final readonly class ImageValue implements Stringable
{
    public function __construct(
        public string $path = '',
    ) {}

    public function isEmpty(): bool
    {
        return $this->path === '';
    }

    public function url(): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        return Storage::disk(config('inlay.disk', 'public'))->url($this->path);
    }

    public function __toString(): string
    {
        return $this->url();
    }
}
