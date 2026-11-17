<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

use WeArePixel\Inlay\Attributes\PageDefinitionMeta;

/**
 * The built-in, unrestricted Page Definition - registered automatically so
 * a developer who never needs Page Definitions at all doesn't have to
 * create one just to give ordinary Pages somewhere to point. Every
 * registered Section is allowed; nothing is required, locked, or seeded by
 * default.
 */
#[PageDefinitionMeta(
    handle: 'default',
    label: 'Standard page',
    description: 'No restrictions on which sections this page may contain.',
)]
final class DefaultPageDefinition extends PageDefinition {}
