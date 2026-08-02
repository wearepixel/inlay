<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Schema;

use BackedEnum;
use InvalidArgumentException;
use LogicException;
use WearePixel\Inlay\Support\SettingOptions;

final class Settings
{
    /** @var array<int, Setting> */
    private array $fields = [];

    private ?Setting $lastField = null;

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

    public function repeater(string $name, Settings $itemSchema): self
    {
        return $this->addField($name, FieldType::Repeater, $itemSchema);
    }

    public function group(string $name, Settings $itemSchema): self
    {
        return $this->addField($name, FieldType::Group, $itemSchema);
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
     * ComponentDefinitionResolver rejects this being used anywhere else.
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
     * field-type call. Used by ComponentDefinitionResolver when merging
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
