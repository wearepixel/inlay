<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;

it('explains that public path and homepage changes take effect immediately, not on publish', function () {
    $page = Page::create(['title' => 'Home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSeeHtml('page-settings-live-notice');
});

it('initialises the Page Settings fields from the page', function () {
    $page = Page::create([
        'title' => 'Home',
        'path' => 'home',
        'meta_title' => 'Welcome home',
        'meta_description' => 'The homepage.',
        'is_homepage' => true,
    ]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSet('pageTitle', 'Home')
        ->assertSet('pagePath', 'home')
        ->assertSet('pageMetaTitle', 'Welcome home')
        ->assertSet('pageMetaDescription', 'The homepage.')
        ->assertSet('pageIsHomepage', true);
});

it('saves title, path, and metadata from the Page Settings panel', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->set('pageTitle', 'Homepage')
        ->set('pagePath', 'welcome')
        ->set('pageMetaTitle', 'Welcome')
        ->set('pageMetaDescription', 'A short summary.')
        ->call('savePageSettings')
        ->assertHasNoErrors();

    $page->refresh();

    expect($page->title)->toBe('Homepage')
        ->and($page->path)->toBe('welcome')
        ->and($page->meta_title)->toBe('Welcome')
        ->and($page->meta_description)->toBe('A short summary.');
});

it('rejects a path already used by another page from the Page Settings panel', function () {
    Page::create(['title' => 'Existing', 'path' => 'existing']);
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->set('pagePath', 'existing')
        ->call('savePageSettings')
        ->assertHasErrors(['pagePath']);

    expect($page->fresh()->path)->toBe('home');
});

it('does not reject a page keeping its own unchanged path', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->set('pageTitle', 'Homepage')
        ->set('pagePath', 'home')
        ->call('savePageSettings')
        ->assertHasNoErrors();

    expect($page->fresh()->title)->toBe('Homepage');
});

it('sets the page as the homepage and unsets any previous one', function () {
    $previousHomepage = Page::create(['title' => 'Old Home', 'is_homepage' => true]);
    $page = Page::create(['title' => 'New Home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->set('pageIsHomepage', true)
        ->call('savePageSettings');

    expect($previousHomepage->fresh()->is_homepage)->toBeFalse()
        ->and($page->fresh()->is_homepage)->toBeTrue();
});

it('unsets the homepage flag when unchecked', function () {
    $page = Page::create(['title' => 'Home', 'is_homepage' => true]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->set('pageIsHomepage', false)
        ->call('savePageSettings');

    expect($page->fresh()->is_homepage)->toBeFalse();
});

it('reflects the active pageRoutes() prefix in the live effective URL as the path is typed', function () {
    Inlay::pageRoutes(prefix: 'pages', view: 'public-page-view');

    $page = Page::create(['title' => 'Home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->set('pagePath', 'about')
        ->assertSee('/pages/about');
});
