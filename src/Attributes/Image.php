<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Attributes;

use Attribute;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Image implements FieldAttribute
{
    /**
     * @param  array{0: string, 1: mixed}|null  $visibleWhen
     */
    public function __construct(
        public ?string $label = null,
        public ?string $help = null,
        public ?array $visibleWhen = null,
    ) {}

    public function toSetting(string $name): Setting
    {
        return new Setting(
            name: $name,
            type: FieldType::Image,
            label: $this->label,
            help: $this->help,
            visibleWhen: $this->visibleWhen,
        );
    }
}
