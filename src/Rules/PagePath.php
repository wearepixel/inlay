<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use WeArePixel\Inlay\Models\Page;

/**
 * Validates a Page's raw, not-yet-normalised public path input: rejects a
 * pasted full URL - a scheme, a query string, or a fragment - with a calm,
 * specific message rather than a generic format error, then checks the
 * *normalised* form for a collision against another Inlay Page. "Another"
 * because editing a Page's own unchanged path must never collide with
 * itself - see $ignorePageId.
 */
final class PagePath implements ValidationRule
{
    public function __construct(private readonly ?int $ignorePageId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $value) === 1 || str_contains($value, '://')) {
            $fail('Public paths should not include a domain or query string.');

            return;
        }

        if (str_contains($value, '?') || str_contains($value, '#')) {
            $fail('Public paths should not include a domain or query string.');

            return;
        }

        $normalized = Page::normalizePath($value);

        if ($normalized === null) {
            return;
        }

        $collision = Page::query()
            ->where('path', $normalized)
            ->when($this->ignorePageId !== null, fn ($query) => $query->where('id', '!=', $this->ignorePageId))
            ->exists();

        if ($collision) {
            $fail('Another Inlay Page already uses this public path.');
        }
    }
}
