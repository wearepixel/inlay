<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

/**
 * The single internal representation a registered PageDefinition compiles
 * into - every Block reference already normalised to its stable registered
 * handle (never a class-string or BlockHandle beyond this point), and
 * required/locked/default blocks folded into the effective allowed set.
 * Everything downstream (the picker, the editor's guardrails, publish
 * validation) operates on this, never on the raw PageDefinition instance.
 */
final readonly class ResolvedPageDefinition
{
    /**
     * @param  array<int, string>|null  $allowedBlocks  null means every registered Block is allowed
     * @param  array<int, string>  $requiredBlocks
     * @param  array<int, string>  $lockedBlocks
     * @param  array<int, string>  $defaultBlocks
     * @param  class-string<PageDefinition>  $definitionClass
     */
    public function __construct(
        public string $handle,
        public string $label,
        public string $description,
        public ?string $icon,
        public ?array $allowedBlocks,
        public array $requiredBlocks,
        public array $lockedBlocks,
        public array $defaultBlocks,
        public string $definitionClass,
    ) {}

    public function allows(string $blockHandle): bool
    {
        return $this->allowedBlocks === null || in_array($blockHandle, $this->allowedBlocks, true);
    }

    public function isRequired(string $blockHandle): bool
    {
        return in_array($blockHandle, $this->requiredBlocks, true);
    }

    public function isLocked(string $blockHandle): bool
    {
        return in_array($blockHandle, $this->lockedBlocks, true);
    }

    public function isUnrestricted(): bool
    {
        return $this->allowedBlocks === null;
    }
}
