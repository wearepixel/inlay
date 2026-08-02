<?php

declare(strict_types=1);

use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

it('resolves a registered slug to its block class', function () {
    $registry = new BlockRegistry(['test-hero' => TestHeroSection::class]);

    expect($registry->resolve('test-hero'))->toBe(TestHeroSection::class)
        ->and($registry->slugs())->toBe(['test-hero']);
});

it('throws for an unregistered slug', function () {
    $registry = new BlockRegistry([]);

    $registry->resolve('missing');
})->throws(InvalidArgumentException::class, 'No block registered for slug [missing].');

it('resolves block picker metadata from a #[BlockMeta] attribute', function () {
    $registry = new BlockRegistry(['test-hero' => TestHeroSection::class]);

    $metadata = $registry->metadata('test-hero');

    expect($metadata->slug)->toBe('test-hero')
        ->and($metadata->label)->toBe('Hero')
        ->and($metadata->category)->toBe('Headers')
        ->and($metadata->description)->toBe('A large banner with a heading and image.')
        ->and($metadata->icon)->toBe('🖼');
});

it('defaults block picker metadata to a slug-derived label and an Uncategorised category with no #[BlockMeta]', function () {
    $registry = new BlockRegistry(['test-faq' => TestFaqSection::class]);

    $metadata = $registry->metadata('test-faq');

    expect($metadata->label)->toBe('Test Faq')
        ->and($metadata->category)->toBe('Uncategorised')
        ->and($metadata->description)->toBe('')
        ->and($metadata->icon)->toBeNull();
});

it('lists metadata for every registered block', function () {
    $registry = new BlockRegistry([
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]);

    expect($registry->all())->toHaveCount(2)
        ->and(array_map(fn ($meta) => $meta->slug, $registry->all()))->toBe(['test-hero', 'test-faq']);
});
