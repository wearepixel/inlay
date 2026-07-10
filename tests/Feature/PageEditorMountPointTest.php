<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('mounts via the documented package Livewire tag, not just Livewire::test() class resolution', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    $html = Blade::render('<livewire:page-builder::page-editor :page="$page" />', ['page' => $page]);

    expect($html)
        ->toContain('wire:id')
        ->and($html)->toContain('Publish');
});
