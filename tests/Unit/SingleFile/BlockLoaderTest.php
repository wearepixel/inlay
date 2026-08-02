<?php

declare(strict_types=1);

use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\SingleFile\BlockLoader;
use WeArePixel\Inlay\Support\BlockSourceType;
use WeArePixel\Inlay\Tests\Fixtures\SingleFile\SingleFileImageSize;

beforeEach(function () {
    $this->loader = new BlockLoader;
    $this->fixturesPath = __DIR__.'/../../Fixtures/single-file';
});

it('compiles a single-file block into a BlockDefinition', function () {
    $definition = $this->loader->load("{$this->fixturesPath}/hero.blade.php");

    expect($definition->handle)->toBe('single-file-hero')
        ->and($definition->name)->toBe('Hero')
        ->and($definition->category)->toBe('Headers')
        ->and($definition->description)->toBe('A single-file hero block.')
        ->and($definition->icon)->toBe('layout-header')
        ->and($definition->sourceType)->toBe(BlockSourceType::SingleFile)
        ->and($definition->sourceIdentifier)->toBe("{$this->fixturesPath}/hero.blade.php");
});

it('uses the Settings declaration as the sole source of defaults, since there is no constructor', function () {
    $definition = $this->loader->load("{$this->fixturesPath}/hero.blade.php");

    expect($definition->defaults())->toBe([
        'heading' => 'Welcome',
        'showCta' => false,
        'ctaLabel' => 'Learn more',
        'imageSize' => SingleFileImageSize::Large,
    ]);
});

it('derives enum options and marks the field enum-backed exactly like a class-based block', function () {
    $definition = $this->loader->load("{$this->fixturesPath}/hero.blade.php");

    $imageSize = $definition->settings->field('imageSize');

    expect($imageSize->type)->toBe(FieldType::Select)
        ->and($imageSize->enumClass)->toBe(SingleFileImageSize::class)
        ->and($imageSize->options)->toBe(['small' => 'Small', 'large' => 'Large']);
});

it('carries a conditional field\'s visibleWhen() through untouched', function () {
    $definition = $this->loader->load("{$this->fixturesPath}/hero.blade.php");

    $ctaLabel = $definition->settings->field('ctaLabel');

    expect($ctaLabel->isVisible(['showCta' => true]))->toBeTrue()
        ->and($ctaLabel->isVisible(['showCta' => false]))->toBeFalse();
});

it('throws for a missing source file', function () {
    $this->loader->load("{$this->fixturesPath}/does-not-exist.blade.php");
})->throws(InvalidArgumentException::class, 'No single-file block found at');

it('throws for a file that never declares a block', function () {
    $this->loader->load("{$this->fixturesPath}/malformed.blade.php");
})->throws(InvalidArgumentException::class, 'does not declare a single-file block');

it('throws for a setting with no declared default', function () {
    $this->loader->load("{$this->fixturesPath}/no-default.blade.php");
})->throws(InvalidArgumentException::class, 'Single-file block [single-file-no-default] setting [heading] has no default()');

it('keeps an explicitly-set name but defaults category/description/icon when metadata is otherwise minimal', function () {
    $definition = $this->loader->load("{$this->fixturesPath}/nested-livewire.blade.php");

    expect($definition->name)->toBe('Nested Livewire')
        ->and($definition->category)->toBe('Uncategorised')
        ->and($definition->description)->toBe('')
        ->and($definition->icon)->toBeNull();
});
