<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\SingleFile;

use WeArePixel\Inlay\Schema\Settings;

/**
 * The fluent declaration a single-file section's own PHP section builds
 * and passes to the global section() function - not a Blade component
 * class, and not related to Illuminate\View\Component. It exists only to
 * be compiled into a WeArePixel\Inlay\Support\Definition by
 * SingleFile\SingleFileLoader; nothing else ever holds a reference to it.
 */
final class Section
{
    private ?string $name = null;

    private string $category = 'Uncategorised';

    private string $description = '';

    private ?string $icon = null;

    private ?string $thumbnail = null;

    /** @var array<int, string> */
    private array $keywords = [];

    private Settings $settings;

    private function __construct(private readonly string $handle)
    {
        $this->settings = Settings::make();
    }

    public static function make(string $handle): self
    {
        return new self($handle);
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function category(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function thumbnail(string $thumbnail): self
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function settings(Settings $settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    /**
     * @param  array<int, string>  $keywords
     */
    public function keywords(array $keywords): self
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
