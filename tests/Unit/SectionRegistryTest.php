<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Support\SectionRegistry;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

it('resolves a registered slug to its section class', function () {
    $registry = new SectionRegistry(['test-hero' => TestHeroSection::class]);

    expect($registry->resolve('test-hero'))->toBe(TestHeroSection::class)
        ->and($registry->slugs())->toBe(['test-hero']);
});

it('throws for an unregistered slug', function () {
    $registry = new SectionRegistry([]);

    $registry->resolve('missing');
})->throws(InvalidArgumentException::class, 'No section registered for slug [missing].');
