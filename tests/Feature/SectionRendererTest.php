<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

it('renders each section through its own Blade view with resolved effective settings', function () {
    $renderer = new SectionRenderer(new BlockRegistry(['test-hero' => TestHeroSection::class]));

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

it('skips a section whose settings fail to construct its block instead of failing the whole page', function () {
    Log::spy();

    $renderer = new SectionRenderer(new BlockRegistry(['test-hero' => TestHeroSection::class]));

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

    $renderer = new SectionRenderer(new BlockRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['handle' => 'not-registered', 'settings' => []],
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Still works', 'layout' => 'image-right']],
    ]);

    expect($html)->toContain('Still works');

    Log::shouldHaveReceived('warning')->once();
});

it('hydrates a stored Image path into an ImageValue that resolves to a real Storage URL', function () {
    $renderer = new SectionRenderer(new BlockRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Welcome', 'layout' => 'image-right', 'photo' => 'inlay/photo.png']],
    ]);

    expect($html)->toContain(Storage::disk('public')->url('inlay/photo.png'));
});

it('leaves a nullable Image field genuinely null when no photo is set, not an always-truthy empty value', function () {
    $renderer = new SectionRenderer(new BlockRegistry(['test-hero' => TestHeroSection::class]));

    $html = $renderer->render([
        ['handle' => 'test-hero', 'settings' => ['heading' => 'Welcome', 'layout' => 'image-right']],
    ]);

    expect($html)->not->toContain('data-test="photo"');
});

it('falls back to the constructor default and keeps rendering when a stored enum value no longer matches a real case', function () {
    Log::spy();

    $renderer = new SectionRenderer(new BlockRegistry(['test-hero' => TestHeroSection::class]));

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
