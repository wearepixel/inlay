<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Schema;

final class Setting
{
    /**
     * Populated by ComponentDefinitionResolver from the owning component's
     * constructor, never by a developer. The constructor is the exclusive
     * source of truth for a top-level setting's nullability and default
     * value - see docs/v1-implementation-specification.md §8, §9.
     */
    public bool $nullable = false;

    public mixed $default = null;

    public bool $hasConstructorDefault = false;

    /**
     * @param  array<string, string>  $options  Resolved label map - populated
     *                                          immediately by Settings::options()
     *                                          whether given as a literal array
     *                                          or derived from an enum's cases.
     * @param  array{0: string, 1: mixed}|null  $visibleWhen
     */
    public function __construct(
        public readonly string $name,
        public readonly FieldType $type,
        public ?string $label = null,
        public array $options = [],
        public readonly ?Settings $itemSchema = null,
        public bool $required = false,
        public ?string $help = null,
        public ?int $maxLength = null,
        public ?array $visibleWhen = null,
        /** Set by hiddenWhen() so a single isVisible() check handles both directions. */
        public bool $negateVisibility = false,
        /** @var class-string|null the enum class explicitly declared via ->options(EnumClass::class), for cross-checking against the constructor's actual type */
        public ?string $declaredEnumClass = null,
        /**
         * Only meaningful for a Setting nested inside a repeater/group item
         * schema, which has no corresponding top-level constructor parameter
         * to be authoritative instead (docs/v1-implementation-specification.md §9).
         */
        public mixed $nestedDefault = null,
        public bool $hasNestedDefault = false,
    ) {}

    /**
     * Purely a UI-rendering concern - never the security boundary. Validation
     * and coercion (ComponentDefinitionResolver) run against every declared
     * setting unconditionally, regardless of what this returns.
     */
    public function isVisible(array $effectiveSettings): bool
    {
        if ($this->visibleWhen === null) {
            return true;
        }

        [$field, $value] = $this->visibleWhen;
        $matches = ($effectiveSettings[$field] ?? null) === $value;

        return $this->negateVisibility ? ! $matches : $matches;
    }
}
