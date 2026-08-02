<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Attributes;

use Attribute;
use WearePixel\Inlay\Schema\FieldType;
use WearePixel\Inlay\Schema\Setting;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Textarea implements FieldAttribute
{
    /**
     * @param  array{0: string, 1: mixed}|null  $visibleWhen
     */
    public function __construct(
        public ?string $label = null,
        public ?string $help = null,
        public bool $required = false,
        public ?array $visibleWhen = null,
    ) {}

    public function toSetting(string $name): Setting
    {
        return new Setting(
            name: $name,
            type: FieldType::Textarea,
            label: $this->label,
            required: $this->required,
            help: $this->help,
            visibleWhen: $this->visibleWhen,
        );
    }
}
