<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console\Install;

/**
 * What one installer step did: changed a file, found it already set up, or
 * could not do it safely and left an instruction for the developer.
 */
final readonly class StepResult
{
    private function __construct(
        public string $status,
        public ?string $todo = null,
    ) {}

    public static function done(): self
    {
        return new self('done');
    }

    public static function alreadySetUp(): self
    {
        return new self('already set up');
    }

    public static function notNeeded(): self
    {
        return new self('not needed');
    }

    public static function needsYou(string $todo): self
    {
        return new self('needs you', $todo);
    }
}
