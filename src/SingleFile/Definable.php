<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\SingleFile;

use WeArePixel\Inlay\Schema\Settings;

/**
 * The fluent declaration at the top of a single-file section or block,
 * compiled into a Definition by SingleFileLoader.
 */
abstract class Definable
{
    private ?string $name = null;

    private string $category = 'Uncategorised';

    private string $description = '';

    private ?string $icon = null;

    private ?string $thumbnail = null;

    /** @var array<int, string> */
    private array $keywords = [];

    private Settings $settings;

    final private function __construct(private readonly string $handle)
    {
        $this->settings = Settings::make();
    }

    public static function make(string $handle): static
    {
        return new static($handle);
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function category(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    public function thumbnail(string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function settings(Settings $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    public function keywords(array $keywords): static
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function handle(): string
    {
        return $this->handle;
    }

    public function resolvedName(): string
    {
        return $this->name ?? str($this->handle)->headline()->toString();
    }

    public function resolvedCategory(): string
    {
        return $this->category;
    }

    public function resolvedDescription(): string
    {
        return $this->description;
    }

    public function resolvedIcon(): ?string
    {
        return $this->icon;
    }

    public function resolvedThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    /**
     * @return array<int, string>
     */
    public function resolvedKeywords(): array
    {
        return $this->keywords;
    }

    public function resolvedSettings(): Settings
    {
        return $this->settings;
    }
}
