<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

use RuntimeException;

/**
 * Thrown by PublishPage when a Page does not satisfy its own Page
 * Definition - the authoritative boundary (docs/inlay/v1/page-definitions), not
 * merely a UI convenience. Carries the full structured violation list so a
 * caller (the editor's publish handler, a test, a console command) can
 * display or assert on exactly what's wrong without re-deriving it.
 */
final class PageDefinitionViolationException extends RuntimeException
{
    /**
     * @param  array<int, PageDefinitionViolation>  $violations
     */
    public function __construct(public readonly array $violations)
    {
        parent::__construct(
            'Cannot publish - this page does not satisfy its Page Definition: '
            .implode(' ', array_map(fn (PageDefinitionViolation $violation) => $violation->message, $violations))
        );
    }
}
