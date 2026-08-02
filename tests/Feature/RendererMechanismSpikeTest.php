<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use WearePixel\BladePageBuilder\Support\LinkValue;
use WearePixel\BladePageBuilder\Tests\Fixtures\Spike\SpikeComponent;
use WearePixel\BladePageBuilder\Tests\Fixtures\Spike\SpikeImageSize;
use WearePixel\BladePageBuilder\Tests\Fixtures\Spike\SpikeInlineWidget;
use WearePixel\BladePageBuilder\Tests\Fixtures\Spike\SpikeThrowingComponent;

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

    return Blade::render(
        "<x-dynamic-component :component=\"\$component\" {$bindings} />",
        ['component' => $alias, 'settings' => $settings],
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

it('mounts and hydrates a nested Livewire component with no page-builder involvement', function () {
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
