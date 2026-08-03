<?php

declare(strict_types=1);

use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Support\LivewireRequirement;

it('throws a clear, actionable exception when Livewire is not installed', function () {
    expect(fn () => LivewireRequirement::ensureInstalled(false))
        ->toThrow(RuntimeException::class, "Inlay's administration interface requires Livewire.");
});

it('does not throw when Livewire is installed', function () {
    LivewireRequirement::ensureInstalled(true);
})->throwsNoExceptions();

it('does not throw when Livewire is actually installed, via the real class_exists check', function () {
    LivewireRequirement::ensureInstalled();
})->throwsNoExceptions();

// Regression: InlayServiceProvider::boot() used to call Livewire::addNamespace()
// unconditionally, fatal-erroring on every request (not just admin ones) if
// Livewire wasn't installed - it's now guarded, and adminRoutes() is the one
// place a host actually needs Livewire, via the same clear exception above.
it('registers admin routes without error when Livewire is actually installed', function () {
    expect(fn () => app(InlayManager::class)->adminRoutes())
        ->not->toThrow(RuntimeException::class);
});
