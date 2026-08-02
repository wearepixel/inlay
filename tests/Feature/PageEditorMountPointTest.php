<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('mounts via the documented package Livewire tag, not just Livewire::test() class resolution', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $html = Blade::render('<livewire:inlay::page-editor :page="$page" />', ['page' => $page]);

    expect($html)
        ->toContain('wire:id')
        ->and($html)->toContain('Publish');
});
