<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use WeArePixel\Inlay\Support\LinkValue;
use WeArePixel\Inlay\Tests\Fixtures\Spike\SpikeComponent;
use WeArePixel\Inlay\Tests\Fixtures\Spike\SpikeImageSize;
use WeArePixel\Inlay\Tests\Fixtures\Spike\SpikeInlineWidget;
use WeArePixel\Inlay\Tests\Fixtures\Spike\SpikeThrowingComponent;

/**
 * A focused spike proving the exact renderer mechanism the v1 specification commits
 * to, against this repo's pinned Laravel/Livewire versions, before any production
 * registry or renderer class is built. See docs/v1-implementation-specification.md
 * for the mechanism this test empirically confirms.
 */
beforeEach(function () {
    Livewire::component('spike-inline-widget', SpikeInlineWidget::class);
});

function spikeComponentAlias(string $class): string
{
    // Deliberately not cached across calls: the application (and its BladeCompiler
    // singleton) is rebuilt fresh per test by Testbench, so a static, cross-test
    // cache would report "already registered" against an alias that no longer
    // exists on the current application instance. Re-registering is idempotent.
    $alias = 'spike-alias-'.md5($class);

    Blade::component($alias, $class);

    return $alias;
}

function spikeRenderComponent(string $class, array $settings): string
{
    $alias = spikeComponentAlias($class);

    $bindings = collect(array_keys($settings))
        ->map(fn (string $key) => ":{$key}=\"\$settings['{$key}']\"")
        ->implode(' ');

    // Deliberately not <x-dynamic-component :component="...">, found by
    // running the FULL suite, not just this file in isolation:
    // Illuminate\View\DynamicComponent memoises its ComponentTagCompiler and
    // every class it has ever resolved into `protected static` properties
    // that live for the whole PHP process. Once any test renders one dynamic
    // component, that memoised compiler's alias snapshot is frozen forever -
    // an alias registered afterward (a different component, a fresh
    // Testbench application boot, a long-running worker meeting a new
    // component type) silently fails to resolve. The alias is interpolated
    // directly as a literal tag name instead - Blade compiles <x-{alias}>
    // as an ordinary component tag against the live compiler at compile
    // time, with no such caching.
    //
    // Blade::render() also caches its compiled output by template TEXT, not
    // by data - two different aliases can produce identical template text
    // (same binding variable names), so deleteCachedView forces a fresh
    // compile every time rather than risking a stale file from a different
    // alias's resolution.
    return Blade::render(
        "<x-{$alias} {$bindings} />",
        ['settings' => $settings],
        deleteCachedView: true,
    );
}

it('renders a runtime-registered Blade component through x-dynamic-component', function () {
    $html = spikeRenderComponent(SpikeComponent::class, ['heading' => 'Hello from the spike']);

    expect($html)->toContain('Hello from the spike');
});

it('passes structured settings as constructor data', function () {
    $html = spikeRenderComponent(SpikeComponent::class, [
        'heading' => 'Structured settings work',
        'imageSize' => SpikeImageSize::Small,
    ]);

    expect($html)
        ->toContain('Structured settings work')
        ->toContain('data-spike-size="small"');
});

it('hydrates backed enums correctly when passed as the enum case', function () {
    $html = spikeRenderComponent(SpikeComponent::class, ['imageSize' => SpikeImageSize::Large]);

    expect($html)->toContain('data-spike-size="large"');
});

it('hydrates LinkValue correctly when passed as the value object', function () {
    $html = spikeRenderComponent(SpikeComponent::class, [
        'action' => new LinkValue(label: 'Shop now', url: '/shop'),
    ]);

    expect($html)
        ->toContain('href="/shop"')
        ->toContain('Shop now');
});

it('renders two instances of the same component independently', function () {
    $first = spikeRenderComponent(SpikeComponent::class, ['heading' => 'First instance']);
    $second = spikeRenderComponent(SpikeComponent::class, ['heading' => 'Second instance']);

    expect($first)->toContain('First instance')->not->toContain('Second instance');
    expect($second)->toContain('Second instance')->not->toContain('First instance');
});

it('resolves an unexposed constructor dependency via the container, never via supplied settings', function () {
    $html = spikeRenderComponent(SpikeComponent::class, ['heading' => 'Container test']);

    // SpikeRepository was never present in $settings - the container filled it in.
    expect($html)->toContain('data-spike-repo="resolved-from-container"');
});

it('mounts and hydrates a nested Livewire component with no inlay involvement', function () {
    $html = spikeRenderComponent(SpikeComponent::class, ['heading' => 'Nested Livewire']);

    expect($html)
        ->toContain('data-spike-widget="true"')
        ->toContain('wire:snapshot');
});

it('isolates a render exception in one component from the next', function () {
    $results = [];

    foreach ([SpikeComponent::class, SpikeThrowingComponent::class, SpikeComponent::class] as $index => $class) {
        try {
            $results[$index] = spikeRenderComponent($class, ['heading' => "Item {$index}"]);
        } catch (Throwable) {
            $results[$index] = null;
        }
    }

    expect($results[0])->not->toBeNull();
    expect($results[1])->toBeNull();
    expect($results[2])->not->toBeNull();
});
