<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

/**
 * One concrete way a Page currently fails to satisfy its own Page
 * Definition - structured so the same value works both as editor-facing
 * copy (calm, no raw class names or handles) and as something a test can
 * assert on directly (docs/inlay/v1/page-definitions#publishing-validation).
 */
final readonly class PageDefinitionViolation
{
    /**
     * @param  'unresolved_definition'|'unresolved_section'|'disallowed_section'|'missing_required_section'  $type
     */
    public function __construct(
        public string $type,
        public string $message,
        public ?string $sectionHandle = null,
    ) {}
}
