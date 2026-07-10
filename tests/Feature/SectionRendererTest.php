<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
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

it('skips a section whose props fail to construct its component instead of failing the whole page', function () {
    Log::spy();

    $renderer = new SectionRenderer(new SectionRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        // Simulates a bypass of PageEditor's validation (e.g. a direct DB
        // write from a stored revision snapshot) reaching the renderer with
        // a type-confused prop: `layout` is typed as `string` on TestHeroSection.
        ['section_type' => 'test-hero', 'props' => ['heading' => 'Broken', 'layout' => ['not' => 'a string']]],
        ['section_type' => 'test-hero', 'props' => ['heading' => 'Still works', 'layout' => 'image-right']],
    ]);

    expect($html)
        ->not->toContain('Broken')
        ->toContain('Still works');

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => $context['section_type'] === 'test-hero',
    );
});

it('skips a section with an unregistered section type instead of failing the whole page', function () {
    Log::spy();

    $renderer = new SectionRenderer(new SectionRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['section_type' => 'not-registered', 'props' => []],
        ['section_type' => 'test-hero', 'props' => ['heading' => 'Still works', 'layout' => 'image-right']],
    ]);

    expect($html)->toContain('Still works');

    Log::shouldHaveReceived('warning')->once();
});
