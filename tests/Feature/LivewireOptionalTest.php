<?php

declare(strict_types=1);

use Livewire\LivewireManager;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Support\LivewireRequirement;

// Livewire is a require-dev dependency here, so the "missing" branch can only
// be forced, never observed. `composer test:no-livewire` proves the real thing
// in a host application that genuinely has none.

it('throws a clear, actionable exception when Livewire is not usable', function () {
    expect(fn () => LivewireRequirement::ensureInstalled(false))
        ->toThrow(RuntimeException::class, "Inlay's administration interface requires Livewire ^4.0.");
});

it('names the install command in the exception', function () {
    expect(fn () => LivewireRequirement::ensureInstalled(false))
        ->toThrow(RuntimeException::class, 'composer require livewire/livewire:^4.0');
});

it('does not throw when Livewire is usable', function () {
    LivewireRequirement::ensureInstalled(true);
})->throwsNoExceptions();

it('does not throw when Livewire is actually installed, via the real capability check', function () {
    LivewireRequirement::ensureInstalled();
})->throwsNoExceptions();

// Livewire 4 is installed here, so the forced failure produces the wording a
// Livewire 3 host would see. The "not installed at all" wording is asserted in
// fixtures/no-livewire/verify.php instead.
it('tells a host with an unusable Livewire to upgrade, not to install', function () {
    expect(fn () => LivewireRequirement::ensureInstalled(false))
        ->toThrow(RuntimeException::class, 'An incompatible version of livewire/livewire is installed');
});

it('probes the namespaced component resolution the admin actually calls', function () {
    expect(LivewireRequirement::isSatisfied())->toBeTrue()
        ->and(method_exists(LivewireManager::class, 'addNamespace'))->toBeTrue();
});

// Regression: boot() used to call Livewire::addNamespace() unconditionally,
// fatal-erroring on every request if Livewire wasn't installed.
it('registers admin routes without error when Livewire is actually installed', function () {
    expect(fn () => app(InlayManager::class)->adminRoutes())
        ->not->toThrow(RuntimeException::class);
});
