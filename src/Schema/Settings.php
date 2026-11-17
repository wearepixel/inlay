<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Schema;

use BackedEnum;
use Closure;
use InvalidArgumentException;
use LogicException;
use WeArePixel\Inlay\Support\SettingOptions;

final class Settings
{
    /** @var array<int, Setting> */
    private array $fields = [];

    private ?Setting $lastField = null;

    /**
     * An explicit override for the tree/settings-header instance label (see
     * SectionInstanceLabel) - a schema-level declaration rather than a
     * per-field one, since it names which *already-declared* field to read,
     * not a new field of its own.
     */
    private ?string $instanceLabelField = null;

    public static function make(): self
    {
        return new self;
    }

    public function text(string $name): self
    {
        return $this->addField($name, FieldType::Text);
    }

    public function textarea(string $name): self
    {
        return $this->addField($name, FieldType::Textarea);
    }

    public function richtext(string $name): self
    {
        return $this->addField($name, FieldType::Richtext);
    }

    public function number(string $name): self
    {
        return $this->addField($name, FieldType::Number);
    }

    public function toggle(string $name): self
    {
        return $this->addField($name, FieldType::Toggle);
    }

    public function image(string $name): self
    {
        return $this->addField($name, FieldType::Image);
    }

    public function link(string $name): self
    {
        return $this->addField($name, FieldType::Link);
    }

    public function select(string $name): self
    {
        return $this->addField($name, FieldType::Select);
    }

    /**
     * The most items a repeater may hold, and the most blocks a blocks area
     * may hold when it sets no lower max().
     */
    public const MAX_ITEMS = 100;

    public function repeater(string $name, Settings $itemSchema): self
    {
        self::assertNoBlocksInside($name, $itemSchema);

        return $this->addField($name, FieldType::Repeater, $itemSchema);
    }

    /**
     * An area editors fill with blocks (headings, text, buttons) in any order.
     * The section's view receives a BlockList: {{ $content }} renders them.
     */
    public function blocks(string $name): self
    {
        return $this->addField($name, FieldType::Blocks);
    }

    /**
     * @param  array<int, string>  $handles  the blocks an editor may add to the preceding blocks field
     */
    public function allow(array $handles): self
    {
        $field = $this->requireLastField('allow');

        if ($field->type !== FieldType::Blocks) {
            throw new LogicException("allow() only applies to a blocks() field; [{$field->name}] is {$field->type->value}.");
        }

        $field->allowedBlocks = array_values($handles);

        return $this;
    }

    public function max(int $count): self
    {
        $field = $this->requireLastField('max');

        if ($field->type !== FieldType::Blocks) {
            throw new LogicException("max() only applies to a blocks() field; [{$field->name}] is {$field->type->value}.");
        }

        $field->maxBlocks = $count;

        return $this;
    }

    public function group(string $name, Settings $itemSchema): self
    {
        self::assertNoBlocksInside($name, $itemSchema);

        return $this->addField($name, FieldType::Group, $itemSchema);
    }

    /**
     * Whether this schema declares a blocks area at any depth.
     */
    public function hasBlocks(): bool
    {
        foreach ($this->fields as $field) {
            if ($field->type === FieldType::Blocks || $field->itemSchema?->hasBlocks()) {
                return true;
            }
        }

        return false;
    }

    /**
     * A blocks area belongs at the top level of a section; the editor and
     * the renderer only look for one there.
     */
    private static function assertNoBlocksInside(string $name, Settings $itemSchema): void
    {
        if ($itemSchema->hasBlocks()) {
            throw new InvalidArgumentException("[{$name}] cannot contain a blocks() field; declare blocks() at the top level of a section.");
        }
    }

    /**
     * Groups every field declared inside $callback under a named, collapsible
     * heading in the settings inspector - purely a presentation concern, not
     * a data shape (unlike group(), which declares a genuine nested-object
     * FieldType::Group setting). Named section() rather than group() to
     * avoid colliding with that existing, unrelated meaning.
     *
     * @param  Closure(self): void  $callback
     */
    public function section(string $label, Closure $callback): self
    {
        $nested = self::make();
        $callback($nested);

        foreach ($nested->fields() as $field) {
            $field->group = $label;
            $this->adopt($field);
        }

        return $this;
    }

    /**
     * Groups every field declared inside $callback under a named tab. Tab
     * navigation only renders in the inspector when a section declares
     * more than one - a single tab() call renders exactly like an ungrouped
     * or section()-only definition. tab() and section() compose: a
     * section() called inside a tab()'s callback produces a collapsible
     * group within that tab.
     *
     * @param  Closure(self): void  $callback
     */
    public function tab(string $label, Closure $callback): self
    {
        $nested = self::make();
        $callback($nested);

        foreach ($nested->fields() as $field) {
            $field->tab = $label;
            $this->adopt($field);
        }

        return $this;
    }

    /**
     * A short, optional callout shown next to the field's label in the
     * inspector (e.g. "New", "Beta") - purely presentational.
     */
    public function badge(string $text): self
    {
        $this->requireLastField('badge')->badge = $text;

        return $this;
    }

    public function label(string $label): self
    {
        $this->requireLastField('label')->label = $label;

        return $this;
    }

    public function help(string $help): self
    {
        $this->requireLastField('help')->help = $help;

        return $this;
    }

    public function required(): self
    {
        $this->requireLastField('required')->required = true;

        return $this;
    }

    public function maxLength(int $maxLength): self
    {
        $this->requireLastField('maxLength')->maxLength = $maxLength;

        return $this;
    }

    /**
     * @param  string  $field  the other setting's name this one's visibility depends on
     * @param  mixed  $value  the value $field must equal for this setting to be visible
     */
    public function visibleWhen(string $field, mixed $value = true): self
    {
        $this->requireLastField('visibleWhen')->visibleWhen = [$field, $value];

        return $this;
    }

    /**
     * The literal inverse of visibleWhen() - not a second mechanism. Stored as
     * the same [field, value] pair with a negation flag, so Setting::isVisible()
     * has exactly one code path regardless of which method declared the condition.
     */
    public function hiddenWhen(string $field, mixed $value = true): self
    {
        $last = $this->requireLastField('hiddenWhen');
        $last->visibleWhen = [$field, $value];
        $last->negateVisibility = true;

        return $this;
    }

    /**
     * A schema-level default, permitted only for a Setting nested inside a
     * repeater/group item schema, which has no top-level constructor
     * parameter to be authoritative instead. See
     * docs/v1-implementation-specification.md §9 for the exact boundary -
     * DefinitionResolver rejects this being used anywhere else.
     */
    public function default(mixed $default): self
    {
        $last = $this->requireLastField('default');
        $last->nestedDefault = $default;
        $last->hasNestedDefault = true;

        return $this;
    }

    /**
     * @param  array<string, string>|class-string<BackedEnum>  $options
     */
    public function options(array|string $options): self
    {
        $last = $this->requireLastField('options');

        if (is_string($options)) {
            if (! enum_exists($options)) {
                throw new InvalidArgumentException("[{$options}] passed to options() is not an enum.");
            }

            $last->declaredEnumClass = $options;
            $last->options = self::labelsFromEnum($options);

            return $this;
        }

        $last->options = $options;

        return $this;
    }

    /**
     * Appends an already-built Setting (from an attribute, or from another
     * Settings instance being merged) rather than building a new one from a
     * field-type call. Used by DefinitionResolver when merging
     * attribute-declared and settings()-declared fields.
     */
    public function adopt(Setting $field): self
    {
        if ($this->field($field->name) !== null) {
            throw new InvalidArgumentException("Duplicate setting [{$field->name}] declared twice in the same settings definition.");
        }

        $this->fields[] = $field;
        $this->lastField = $field;

        return $this;
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @return array<string, string>
     */
    public static function labelsFromEnum(string $enumClass): array
    {
        $labels = [];

        foreach ($enumClass::cases() as $case) {
            $labels[$case->value] = $case instanceof SettingOptions
                ? $case->label()
                : str($case->name)->headline()->toString();
        }

        return $labels;
    }

    /**
     * Names a short text field whose value should identify this section's
     * instance in the tree and settings header (e.g. "Hero - 'Welcome'")
     * instead of the deterministic heading/title/name/label heuristic
     * SectionInstanceLabel otherwise falls back to. Only meaningful for a
     * FieldType::Text field - naming anything else is simply ignored, the
     * same as the heuristic ignoring one.
     */
    public function instanceLabel(string $field): self
    {
        $this->instanceLabelField = $field;

        return $this;
    }

    public function instanceLabelField(): ?string
    {
        return $this->instanceLabelField;
    }

    public function field(string $name): ?Setting
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<int, Setting>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    private function addField(string $name, FieldType $type, ?Settings $itemSchema = null): self
    {
        if ($this->field($name) !== null) {
            throw new InvalidArgumentException("Duplicate setting [{$name}] declared twice in the same settings definition.");
        }

        $field = new Setting($name, $type, itemSchema: $itemSchema);

        $this->fields[] = $field;
        $this->lastField = $field;

        return $this;
    }

    private function requireLastField(string $method): Setting
    {
        if (! $this->lastField) {
            throw new LogicException("{$method}() must follow a field definition.");
        }

        return $this->lastField;
    }
}
