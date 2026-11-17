<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Schema;

final class Setting
{
    /**
     * Populated by DefinitionResolver from the owning section's
     * constructor, never by a developer. The constructor is the exclusive
     * source of truth for a top-level setting's nullability and default
     * value - see docs/v1-implementation-specification.md §8, §9.
     */
    public bool $nullable = false;

    public mixed $default = null;

    public bool $hasConstructorDefault = false;

    /**
     * @var class-string<\BackedEnum>|null Populated by
     *                                     DefinitionResolver when the
     *                                     constructor parameter is
     *                                     enum-typed - never set directly.
     */
    public ?string $enumClass = null;

    /**
     * The named group/tab this field was declared under via
     * Settings::group()/tab() - null for a field declared directly, which
     * renders without a group wrapper or tab bar. Purely a presentation
     * concern for the settings inspector.
     */
    public ?string $group = null;

    public ?string $tab = null;

    /**
     * A short, optional callout shown next to the field's label in the
     * inspector (e.g. "New", "Beta") - purely presentational.
     */
    public ?string $badge = null;

    /**
     * For a blocks field: the block handles an editor may add. Empty allows
     * every registered block.
     *
     * @var array<int, string>
     */
    public array $allowedBlocks = [];

    /** For a blocks field: the most blocks an editor may add. */
    public ?int $maxBlocks = null;

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
     * and coercion (DefinitionResolver) run against every declared
     * setting unconditionally, regardless of what this returns.
     *
     * @param  array<string, mixed>  $effectiveSettings
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

    /**
     * The client-side equivalent of isVisible(), reading Livewire's live
     * editingProps through Alpine's $wire so a conditional field shows or
     * hides without a round trip. $scope is the JS path to the object the
     * field lives in - a block's settings, for a block's field.
     */
    public function visibilityExpression(string $scope = '$wire.editingProps'): ?string
    {
        if ($this->visibleWhen === null) {
            return null;
        }

        [$field, $value] = $this->visibleWhen;
        $comparison = "{$scope}['{$field}'] === ".json_encode($value);

        return $this->negateVisibility ? "!({$comparison})" : $comparison;
    }
}
