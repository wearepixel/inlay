<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

/**
 * The single internal representation a registered PageDefinition compiles
 * into - every Section reference already normalised to its stable registered
 * handle (never a class-string or SectionHandle beyond this point), and
 * required/locked/default sections folded into the effective allowed set.
 * Everything downstream (the picker, the editor's guardrails, publish
 * validation) operates on this, never on the raw PageDefinition instance.
 */
final readonly class ResolvedPageDefinition
{
    /**
     * @param  array<int, string>|null  $allowedSections  null means every registered Section is allowed
     * @param  array<int, string>  $requiredSections
     * @param  array<int, string>  $lockedSections
     * @param  array<int, string>  $defaultSections
     * @param  class-string<PageDefinition>  $definitionClass
     */
    public function __construct(
        public string $handle,
        public string $label,
        public string $description,
        public ?string $icon,
        public ?array $allowedSections,
        public array $requiredSections,
        public array $lockedSections,
        public array $defaultSections,
        public string $definitionClass,
    ) {}

    public function allows(string $sectionHandle): bool
    {
        return $this->allowedSections === null || in_array($sectionHandle, $this->allowedSections, true);
    }

    public function isRequired(string $sectionHandle): bool
    {
        return in_array($sectionHandle, $this->requiredSections, true);
    }

    public function isLocked(string $sectionHandle): bool
    {
        return in_array($sectionHandle, $this->lockedSections, true);
    }

    public function isUnrestricted(): bool
    {
        return $this->allowedSections === null;
    }
}
