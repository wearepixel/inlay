<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Support\SectionRegistry;
use WearePixel\BladePageBuilder\Support\SectionRenderer;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

it('renders each section through its own Blade view with resolved class mapping', function () {
    $renderer = new SectionRenderer(new SectionRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['section_type' => 'test-hero', 'props' => ['heading' => 'Welcome', 'layout' => 'image-left']],
        ['section_type' => 'test-hero', 'props' => ['heading' => 'Bye', 'layout' => 'image-right']],
    ]);

    expect($html)
        ->toContain('Welcome')
        ->toContain('flex-row-reverse')
        ->toContain('Bye')
        ->toContain('flex-row"');
});
