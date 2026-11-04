<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Composition;

use RuntimeException;

final class InvalidCompositionException extends RuntimeException
{
    /**
     * @param  array<int, CompositionError>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('The composition is invalid: '.implode(' ', array_map(
            fn (CompositionError $error) => "{$error->path}: {$error->message}",
            $errors,
        )));
    }
}
