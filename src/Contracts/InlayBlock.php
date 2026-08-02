<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Contracts;

/**
 * A marker interface - deliberately empty. A block may declare its
 * editor-facing settings via constructor-property attributes, an optional
 * `public static function settings(): Settings` method, or both. Requiring
 * settings() on this interface would make it impossible for an
 * attribute-only block to satisfy the contract, since PHP cannot make
 * an interface method simultaneously required and optional.
 */
interface InlayBlock {}
