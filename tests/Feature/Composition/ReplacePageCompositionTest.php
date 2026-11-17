<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use WeArePixel\Inlay\Actions\ReplacePageComposition;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Tests\Fixtures\TestCollectionPageDefinition;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]]);
    Inlay::singleFileSection(__DIR__.'/../../Fixtures/single-file/composition/promo.blade.php');
    // Required + locked + default: test-hero. Allowed: test-hero, test-faq.
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);
});

function replaceComposition(Page $page, array $sections): void
{
    app(ReplacePageComposition::class)->execute($page, $sections);
}

function compositionErrors(Page $page, array $sections): array
{
    try {
        replaceComposition($page, $sections);
    } catch (InvalidCompositionException $exception) {
        return collect($exception->errors)->map(fn ($error) => $error->path.': '.$error->message)->all();
    }

    throw new RuntimeException('Expected the composition to be rejected.');
}

function storedComposition(Page $page): array
{
    return $page->sections()->orderBy('order')->get()
        ->map(fn ($section) => [$section->handle, $section->settings])
        ->all();
}

it('replaces every section in the given order, storing only the settings supplied', function () {
    $page = Page::create(['title' => 'Landing']);
    $page->sections()->create(['handle' => 'test-faq', 'order' => 0, 'settings' => []]);

    replaceComposition($page, [
        ['section' => 'promo', 'settings' => ['heading' => 'Hello', 'cta' => ['label' => 'Go', 'url' => '/go']]],
        ['section' => 'test-hero'],
    ]);

    expect(storedComposition($page))->toBe([
        ['promo', ['heading' => 'Hello', 'cta' => ['label' => 'Go', 'url' => '/go', 'openInNewTab' => false]]],
        ['test-hero', []],
    ]);
});

it('leaves the page untouched when any section is invalid', function () {
    $page = Page::create(['title' => 'Landing']);
    $page->sections()->create(['handle' => 'test-faq', 'order' => 0, 'settings' => []]);

    $errors = compositionErrors($page, [
        ['section' => 'promo', 'settings' => ['heading' => 'Fine']],
        ['section' => 'does-not-exist'],
    ]);

    expect($errors)->toBe(['sections.1.section: No section is registered with the handle "does-not-exist".'])
        ->and(storedComposition($page))->toBe([['test-faq', []]]);
});

it('rejects a section the page definition does not allow', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    $errors = compositionErrors($page, [
        ['section' => 'test-hero'],
        ['section' => 'promo'],
    ]);

    expect($errors[0])->toStartWith('sections.1.section: Promo is not allowed on Test Collection Page')
        ->and(storedComposition($page))->toBe([['test-hero', []]]);
});

it('rejects moving a locked section from its current position', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => []]);

    $errors = compositionErrors($page, [
        ['section' => 'test-faq'],
        ['section' => 'test-hero'],
    ]);

    expect($errors[0])->toStartWith('sections.0: Hero is locked at position 0')
        ->and(storedComposition($page))->toBe([['test-hero', []]]);
});

it('rejects a composition that leaves out a required section', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'test-collection-page-definition']);

    $errors = compositionErrors($page, [['section' => 'test-faq']]);

    expect($errors)->toBe(['sections: Hero is required by Test Collection Page. Add a "test-hero" section.'])
        ->and($page->sections()->count())->toBe(0);
});

it('rejects every change when the page definition no longer resolves', function () {
    $page = Page::create(['title' => 'Landing', 'definition' => 'no-longer-registered']);

    $errors = compositionErrors($page, [['section' => 'promo']]);

    expect($errors[0])->toStartWith('definition:')
        ->and($page->sections()->count())->toBe(0);
});

it('reports each settings problem with its dotted path', function (array $settings, string $expectedPath) {
    $page = Page::create(['title' => 'Landing']);

    $errors = compositionErrors($page, [['section' => 'promo', 'settings' => $settings]]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toStartWith($expectedPath.': ')
        ->and($page->sections()->count())->toBe(0);
})->with([
    'too long' => [['heading' => str_repeat('a', 21)], 'sections.0.settings.heading'],
    'empty required' => [['heading' => '  '], 'sections.0.settings.heading'],
    'unknown key' => [['colour' => 'red'], 'sections.0.settings.colour'],
    'non-numeric number' => [['columns' => 'three'], 'sections.0.settings.columns'],
    'non-boolean toggle' => [['dark' => 'yes'], 'sections.0.settings.dark'],
    'unknown option' => [['align' => 'right'], 'sections.0.settings.align'],
    'javascript url' => [['cta' => ['label' => 'Go', 'url' => 'javascript:alert(1)']], 'sections.0.settings.cta.url'],
    'unknown link key' => [['cta' => ['href' => '/x']], 'sections.0.settings.cta.href'],
    'repeater item required' => [['items' => [['link' => ['url' => '/x']]]], 'sections.0.settings.items.0.title'],
    'repeater not a list' => [['items' => ['title' => 'x']], 'sections.0.settings.items'],
    'group wrong type' => [['badge' => ['text' => ['nope']]], 'sections.0.settings.badge.text'],
    'missing image' => [['photo' => 'inlay/missing.png'], 'sections.0.settings.photo'],
    'image path traversal' => [['photo' => '../.env'], 'sections.0.settings.photo'],
    'public image traversal' => [['photo' => '/images/../../.env'], 'sections.0.settings.photo'],
    'protocol-relative image' => [['photo' => '//evil.example/x.png'], 'sections.0.settings.photo'],
    'missing public image' => [['photo' => '/images/missing.png'], 'sections.0.settings.photo'],
    'infinite number' => [['columns' => '1e999'], 'sections.0.settings.columns'],
]);

it('accepts an image path that exists on the configured disk', function () {
    Storage::fake('public');
    Storage::disk('public')->put('inlay/hero.png', 'png');
    $page = Page::create(['title' => 'Landing']);

    replaceComposition($page, [['section' => 'promo', 'settings' => ['photo' => 'inlay/hero.png']]]);

    expect(storedComposition($page))->toBe([['promo', ['photo' => 'inlay/hero.png']]]);
});

it('stores repeater and group values in their coerced shape', function () {
    $page = Page::create(['title' => 'Landing']);

    replaceComposition($page, [[
        'section' => 'promo',
        'settings' => [
            'columns' => '4',
            'items' => [['title' => 'One', 'link' => ['url' => 'https://example.com', 'label' => 'More']]],
            'badge' => ['visible' => true],
        ],
    ]]);

    expect(storedComposition($page)[0][1])->toBe([
        'columns' => 4,
        'items' => [['title' => 'One', 'link' => ['label' => 'More', 'url' => 'https://example.com', 'openInNewTab' => false]]],
        'badge' => ['visible' => true],
    ]);
});

it('strips script, event handlers and dangerous links from rich text before storing it', function () {
    $page = Page::create(['title' => 'Landing']);

    replaceComposition($page, [[
        'section' => 'promo',
        'settings' => ['body' => '<p onclick="steal()">Hello <strong>there</strong> <a href="javascript:alert(1)">bad</a> <a href="/menu">menu</a></p><script>alert(1)</script><img src=x onerror="alert(1)">'],
    ]]);

    $body = storedComposition($page)[0][1]['body'];

    expect($body)->not->toContain('<script')
        ->and($body)->not->toContain('onclick')
        ->and($body)->not->toContain('onerror')
        ->and($body)->not->toContain('javascript:')
        ->and($body)->toContain('<strong>there</strong>', 'href="/menu"');
});

it('treats a null inside a repeater item as "use the default"', function () {
    $page = Page::create(['title' => 'Landing']);

    replaceComposition($page, [['section' => 'promo', 'settings' => ['items' => [['title' => 'One', 'link' => null]]]]]);

    expect(storedComposition($page)[0][1]['items'])->toBe([['title' => 'One']]);
});

it('stores an int-backed enum select whether it arrives as an int or a numeric string', function (int|string $value) {
    Inlay::singleFileSection(__DIR__.'/../../Fixtures/single-file/composition/ranked.blade.php');
    $page = Page::create(['title' => 'Landing']);

    replaceComposition($page, [['section' => 'ranked', 'settings' => ['priority' => $value]]]);

    expect(storedComposition($page)[0][1])->toBe(['priority' => 2]);
})->with([2, '2']);

it('refuses to replace a page that still holds a section whose section is no longer registered', function () {
    $page = Page::create(['title' => 'Landing']);
    $page->sections()->create(['handle' => 'retired-section', 'order' => 0, 'settings' => ['heading' => 'Keep me']]);

    $errors = compositionErrors($page, [['section' => 'promo']]);

    expect($errors[0])->toStartWith('sections.0: The saved section at index 0 uses "retired-section"')
        ->and(storedComposition($page))->toBe([['retired-section', ['heading' => 'Keep me']]]);
});

it('refuses an existing file that is outside the inlay image folder', function () {
    Storage::fake('public');
    Storage::disk('public')->put('private/contract.png', 'png');
    $page = Page::create(['title' => 'Landing']);

    $errors = compositionErrors($page, [['section' => 'promo', 'settings' => ['photo' => 'private/contract.png']]]);

    expect($errors)->toBe(['sections.0.settings.photo: Must be a relative path on the Inlay disk, as returned by inlay-upload-image.']);
});

it('accepts a root-relative image the application serves from its public directory', function () {
    $file = public_path('inlay-test-image.png');
    file_put_contents($file, 'png');
    $page = Page::create(['title' => 'Landing']);

    try {
        replaceComposition($page, [['section' => 'promo', 'settings' => ['photo' => '/inlay-test-image.png']]]);
    } finally {
        @unlink($file);
    }

    expect(storedComposition($page)[0][1]['photo'])->toBe('/inlay-test-image.png');
});
