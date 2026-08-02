<?php

declare(strict_types=1);

namespace WearePixel\Inlay\Support;

use WearePixel\Inlay\Schema\Settings;

/**
 * The single internal representation every registered component compiles
 * into, regardless of whether it was authored as a class + Blade view or as
 * a single Blade file - the registry, the picker, settings resolution,
 * validation, persistence, preview, rendering, publishing, and revisions
 * all operate on this, and none of them branch on which authoring style
 * produced it. Only SectionRenderer's actual render step reads $sourceType,
 * because a PHP class and a bare Blade file are rendered through genuinely
 * different mechanisms.
 */
final readonly class ComponentDefinition
{
    public function __construct(
        public string $handle,
        public string $name,
        public string $description,
        public string $category,
        public ?string $icon,
        public ?string $thumbnail,
        public ComponentSourceType $sourceType,
        /**
         * A class-string for a class-based component, or an absolute Blade
         * file path for a single-file one - only ever consumed by
         * SectionRenderer's rendering step.
         */
        public string $sourceIdentifier,
        public Settings $settings,
        /**
         * Reserved for future settings-version migration (not yet
         * implemented - see docs/components.md). Always 1 today for both
         * authoring styles.
         */
        public int $settingsVersion = 1,
        /**
         * @var array<int, string> extra terms the component picker's search matches against
         */
        public array $keywords = [],
    ) {}

    /**
     * The resolved default value for every declared setting, keyed by
     * name - the same defaults SectionRenderer falls back to for a field
     * absent from stored overrides.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach ($this->settings->fields() as $setting) {
            $defaults[$setting->name] = $setting->default;
        }

        return $defaults;
    }

    public function toMetadata(): ComponentMetadata
    {
        return new ComponentMetadata(
            slug: $this->handle,
            label: $this->name,
            category: $this->category,
            description: $this->description,
            icon: $this->icon,
            thumbnail: $this->thumbnail,
            keywords: $this->keywords,
        );
    }
}
