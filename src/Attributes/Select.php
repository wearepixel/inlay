<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Attributes;

use Attribute;
use WearePixel\Inlay\Schema\FieldType;
use WearePixel\Inlay\Schema\Setting;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Select implements FieldAttribute
{
    /**
     * @param  array<string, string>|null  $options  Only needed when the constructor
     *                                               parameter's type is not a backed
     *                                               enum - an enum's options are
     *                                               derived automatically at registration.
     */
    public function __construct(
        public ?string $label = null,
        public ?string $help = null,
        public bool $required = false,
        public ?array $options = null,
        public ?array $visibleWhen = null,
    ) {}

    public function toSetting(string $name): Setting
    {
        return new Setting(
            name: $name,
            type: FieldType::Select,
            label: $this->label,
            options: $this->options ?? [],
            required: $this->required,
            help: $this->help,
            visibleWhen: $this->visibleWhen,
        );
    }
}
