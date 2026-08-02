<?php

declare(strict_types=1);

use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\SingleFile\ComponentLoader;
use WeArePixel\Inlay\Support\ComponentSourceType;
use WeArePixel\Inlay\Tests\Fixtures\SingleFile\SingleFileImageSize;

beforeEach(function () {
    $this->loader = new ComponentLoader;
    $this->fixturesPath = __DIR__.'/../../Fixtures/single-file';
});

it('compiles a single-file component into a ComponentDefinition', function () {
    $definition = $this->loader->load("{$this->fixturesPath}/hero.blade.php");

    expect($definition->handle)->toBe('single-file-hero')
        ->and($definition->name)->toBe('Hero')
        ->and($definition->category)->toBe('Headers')
        ->and($definition->description)->toBe('A single-file hero component.')
        ->and($definition->icon)->toBe('layout-header')
        ->and($definition->sourceType)->toBe(ComponentSourceType::SingleFile)
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

it('derives enum options and marks the field enum-backed exactly like a class-based component', function () {
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
})->throws(InvalidArgumentException::class, 'No single-file component found at');

it('throws for a file that never declares a component', function () {
    $this->loader->load("{$this->fixturesPath}/malformed.blade.php");
})->throws(InvalidArgumentException::class, 'does not declare a single-file component');

it('throws for a setting with no declared default', function () {
    $this->loader->load("{$this->fixturesPath}/no-default.blade.php");
})->throws(InvalidArgumentException::class, 'Single-file component [single-file-no-default] setting [heading] has no default()');

it('keeps an explicitly-set name but defaults category/description/icon when metadata is otherwise minimal', function () {
    $definition = $this->loader->load("{$this->fixturesPath}/nested-livewire.blade.php");

    expect($definition->name)->toBe('Nested Livewire')
        ->and($definition->category)->toBe('Uncategorised')
        ->and($definition->description)->toBe('')
        ->and($definition->icon)->toBeNull();
});
