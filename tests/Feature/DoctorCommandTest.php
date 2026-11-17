<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

it('fails and says how to fix an unguarded admin with no sections', function () {
    $this->artisan('inlay:doctor')
        ->expectsOutputToContain("Set config('inlay.admin.ability')")
        ->expectsOutputToContain('Add sections to resources/views/inlay/sections')
        ->assertFailed();
});

it('fails when the configured ability has no gate behind it', function () {
    config(['inlay.admin.ability' => 'inlay.manage-pages', 'inlay.sections' => ['hero' => TestHeroSection::class]]);
    app()->forgetInstance(\WeArePixel\Inlay\Support\SectionRegistry::class);

    $this->artisan('inlay:doctor')->assertFailed();
});

it('passes once the admin is guarded and sections are registered', function () {
    config(['inlay.admin.ability' => 'inlay.manage-pages', 'inlay.sections' => ['hero' => TestHeroSection::class]]);
    app()->forgetInstance(\WeArePixel\Inlay\Support\SectionRegistry::class);
    Gate::define('inlay.manage-pages', fn ($user = null) => true);
    Inlay::previewView('host-preview');

    $this->artisan('inlay:doctor')
        ->expectsOutputToContain('Inlay is ready.')
        ->assertSuccessful();
});
