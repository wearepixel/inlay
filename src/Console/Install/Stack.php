<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console\Install;

/**
 * What the installer found in the host application.
 */
final readonly class Stack
{
    /**
     * @param  string  $css  tailwind, bootstrap or plain
     * @param  array<int, string>  $layouts  layout views the pages could render inside, as "component:layouts.app" or "extends:layouts.app"
     * @param  array<int, string>  $viteEntries  stylesheet and script entries the public pages should load
     */
    public function __construct(
        public string $css,
        public bool $hasLivewire,
        public bool $hasAuth,
        public bool $hasFilament,
        public bool $hasMcp,
        public bool $isFreshApp,
        public array $layouts,
        public array $viteEntries,
    ) {}

    public function describeCss(): string
    {
        return match ($this->css) {
            'tailwind' => 'Tailwind CSS',
            'bootstrap' => 'Bootstrap',
            default => 'plain CSS',
        };
    }
}
