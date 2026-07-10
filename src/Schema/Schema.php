<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Schema;

use Closure;
use LogicException;

final class Schema
{
    /** @var array<int, Field> */
    private array $fields = [];

    private ?Field $lastField = null;

    public static function make(): self
    {
        return new self;
    }

    public function text(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Text, $label);
    }

    public function textarea(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Textarea, $label);
    }

    public function richtext(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Richtext, $label);
    }

    public function image(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Image, $label);
    }

    public function link(string $name, string $label): self
    {
        return $this->addField($name, FieldType::Link, $label);
    }

    /**
     * @param  array<string, string>  $options
     */
    public function select(string $name, string $label, array $options): self
    {
        return $this->addField($name, FieldType::Select, $label, ['options' => $options]);
    }

    public function repeater(string $name, string $label, Schema $itemSchema): self
    {
        return $this->addField($name, FieldType::Repeater, $label, itemSchema: $itemSchema);
    }

    public function group(string $name, string $label, Schema $itemSchema): self
    {
        return $this->addField($name, FieldType::Group, $label, itemSchema: $itemSchema);
    }

    public function mapsToClass(Closure $resolver): self
    {
        if (! $this->lastField) {
            throw new LogicException('mapsToClass() must follow a field definition.');
        }

        $this->lastField->classResolver = $resolver;

        return $this;
    }

    public function field(string $name): ?Field
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<int, Field>
     */
    public function fields(): array
    {
        return $this->fields;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function addField(string $name, FieldType $type, string $label, array $options = [], ?Schema $itemSchema = null): self
    {
        $field = new Field($name, $type, $label, $options, $itemSchema);

        $this->fields[] = $field;
        $this->lastField = $field;

        return $this;
    }
}
