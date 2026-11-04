<?php

declare(strict_types=1);

use WeArePixel\Inlay\Styling\CompiledStylesheets;

it('reads class names, unescaped, from selectors but not from values or comments', function () {
    $classes = array_keys((new CompiledStylesheets)->parseClasses((string) file_get_contents(__DIR__.'/../../Fixtures/css/app.css')));

    expect($classes)->toEqualCanonicalizing([
        'flex', 'bg-brand-600', 'py-20', 'w-1/2', 'hover:bg-gray-950', 'lg:grid-cols-2',
        'text-[#ff0000]', '10xl', 'bg-[url(hero.png)]', 'p-1.5', 'fixed', 'inset-0',
    ]);
});

it('reads theme tokens but not internal variables', function () {
    $tokens = (new CompiledStylesheets)->parseTokens((string) file_get_contents(__DIR__.'/../../Fixtures/css/app.css'));

    expect($tokens)->toBe([
        '--color-brand-600' => 'oklch(51% .26 277)',
        '--color-gray-950' => '#030712',
        '--spacing' => '.25rem',
        '--font-sans' => 'Inter,sans-serif',
    ]);
});

it('allows only configured and compiled classes, and suggests near misses', function () {
    config(['inlay.styling.stylesheets' => [__DIR__.'/../../Fixtures/css/app.css'], 'inlay.styling.vite_manifest' => '/nope', 'inlay.styling.extra_classes' => ['js-carousel']]);
    $styles = new CompiledStylesheets;

    expect($styles->allows('lg:grid-cols-2'))->toBeTrue()
        ->and($styles->allows('js-carousel'))->toBeTrue()
        ->and($styles->allows('bg-red-500'))->toBeFalse()
        ->and($styles->suggest('bg-brand-500'))->toContain('bg-brand-600');
});

it('never allows denied classes, even when the CSS defines them', function () {
    config(['inlay.styling.stylesheets' => [__DIR__.'/../../Fixtures/css/app.css'], 'inlay.styling.vite_manifest' => '/nope']);

    expect((new CompiledStylesheets)->allows('inset-0'))->toBeTrue()
        ->and((new CompiledStylesheets)->allows('fixed'))->toBeFalse();
});

it('does not read attribute selector values as classes', function () {
    expect(array_keys((new CompiledStylesheets)->parseClasses('[data-url*="open.spotify.com"] .embed{x:y} .a[href$=".pdf"]{x:y}')))->toBe(['embed', 'a']);
});

it('limits the allowlist to the configured Vite entries', function () {
    $dir = sys_get_temp_dir().'/inlay-vite-'.bin2hex(random_bytes(3));
    mkdir($dir.'/assets', 0777, true);
    file_put_contents($dir.'/assets/app.css', '.site{x:y}');
    file_put_contents($dir.'/assets/admin.css', '.admin{x:y}');
    file_put_contents($dir.'/manifest.json', json_encode([
        'resources/css/app.css' => ['file' => 'assets/app.css'],
        'resources/css/admin.css' => ['file' => 'assets/admin.css'],
    ]));
    config(['inlay.styling.vite_manifest' => $dir.'/manifest.json', 'inlay.styling.vite_entries' => ['resources/css/app.css']]);

    expect(array_keys((new CompiledStylesheets)->classes()))->toBe(['site']);
});
