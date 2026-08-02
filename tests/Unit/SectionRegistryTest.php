<?php

declare(strict_types=1);

use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

it('resolves a registered slug to its section class', function () {
    $registry = new SectionRegistry(['test-hero' => TestHeroSection::class]);

    expect($registry->resolve('test-hero'))->toBe(TestHeroSection::class)
        ->and($registry->slugs())->toBe(['test-hero']);
});

it('throws for an unregistered slug', function () {
    $registry = new SectionRegistry([]);

    $registry->resolve('missing');
})->throws(InvalidArgumentException::class, 'No section registered for slug [missing].');

it('resolves component picker metadata from a #[ComponentMeta] attribute', function () {
    $registry = new SectionRegistry(['test-hero' => TestHeroSection::class]);

    $metadata = $registry->metadata('test-hero');

    expect($metadata->slug)->toBe('test-hero')
        ->and($metadata->label)->toBe('Hero')
        ->and($metadata->category)->toBe('Headers')
        ->and($metadata->description)->toBe('A large banner with a heading and image.')
        ->and($metadata->icon)->toBe('🖼');
});

it('defaults component picker metadata to a slug-derived label and an Uncategorised category with no #[ComponentMeta]', function () {
    $registry = new SectionRegistry(['test-faq' => TestFaqSection::class]);

    $metadata = $registry->metadata('test-faq');

    expect($metadata->label)->toBe('Test Faq')
        ->and($metadata->category)->toBe('Uncategorised')
        ->and($metadata->description)->toBe('')
        ->and($metadata->icon)->toBeNull();
});

it('lists metadata for every registered component', function () {
    $registry = new SectionRegistry([
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]);

    expect($registry->all())->toHaveCount(2)
        ->and(array_map(fn ($meta) => $meta->slug, $registry->all()))->toBe(['test-hero', 'test-faq']);
});
