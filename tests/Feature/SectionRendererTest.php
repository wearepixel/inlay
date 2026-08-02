<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use WearePixel\Inlay\Support\SectionRegistry;
use WearePixel\Inlay\Support\SectionRenderer;
use WearePixel\Inlay\Tests\Fixtures\TestHeroSection;

it('renders each section through its own Blade view with resolved effective settings', function () {
    $renderer = new SectionRenderer(new SectionRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Welcome', 'layout' => 'image-left']],
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Bye', 'layout' => 'image-right']],
    ]);

    expect($html)
        ->toContain('Welcome')
        ->toContain('flex-row-reverse')
        ->toContain('Bye')
        ->toContain('flex-row"');
});

it('skips a section whose settings fail to construct its component instead of failing the whole page', function () {
    Log::spy();

    $renderer = new SectionRenderer(new SectionRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        // Simulates a bypass of PageEditor's validation reaching the
        // renderer with a type-confused setting: `heading` is a plain
        // `string` constructor parameter on TestHeroSection.
        ['handle' => 'test-hero', 'settings' => ['heading' => ['not' => 'a string']]],
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Still works', 'layout' => 'image-right']],
    ]);

    expect($html)
        ->not->toContain('Broken')
        ->toContain('Still works');

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => $context['handle'] === 'test-hero',
    );
});

it('skips a section with an unregistered handle instead of failing the whole page', function () {
    Log::spy();

    $renderer = new SectionRenderer(new SectionRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['handle' => 'not-registered', 'settings' => []],
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Still works', 'layout' => 'image-right']],
    ]);

    expect($html)->toContain('Still works');

    Log::shouldHaveReceived('warning')->once();
});

it('falls back to the constructor default and keeps rendering when a stored enum value no longer matches a real case', function () {
    Log::spy();

    $renderer = new SectionRenderer(new SectionRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Orphaned layout', 'layout' => 'no-longer-a-case']],
    ]);

    expect($html)
        ->toContain('Orphaned layout')
        ->toContain('flex-row"'); // TestHeroLayout::ImageRight's default class

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn (string $message, array $context) => $context['setting'] === 'layout',
    );
});
