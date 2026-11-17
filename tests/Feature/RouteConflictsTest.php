<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\PageCreate;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Livewire\PageIndex;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\RouteConflicts;

it('names the earlier route that answers a page\'s url instead of the page', function () {
    Route::get('/about', fn () => 'theirs')->name('about');
    Inlay::pageRoutes();

    expect(app(RouteConflicts::class)->routeTaking('/about'))->toContain('"about" route')
        ->and(app(RouteConflicts::class)->routeTaking('/pricing'))->toBeNull();
});

it('reports nothing when Inlay is not serving pages itself', function () {
    Route::get('/about', fn () => 'theirs');

    expect(app(RouteConflicts::class)->routeTaking('/about'))->toBeNull();
});

it('finds app routes registered after the page route, which never run', function () {
    Inlay::pageRoutes();
    Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
    Route::get('/posts/{post}', fn () => 'post');

    expect(app(RouteConflicts::class)->routesHiddenByPages())->toBe(['the "dashboard" route (/dashboard, a closure)']);
});

it('warns in the editor, the new page form and the page list when a page\'s url is taken', function () {
    Route::get('/about', fn () => 'theirs')->name('about');
    Inlay::pageRoutes();
    $page = Page::create(['title' => 'About', 'path' => 'about']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->assertSee('data-test="route-conflict"', false)
        ->assertSee('&quot;about&quot; route', false);

    Livewire::test(PageCreate::class)
        ->set('path', 'about')
        ->assertSee('data-test="route-conflict"', false);

    Livewire::test(PageIndex::class)->assertSee('data-test="route-conflict-'.$page->id.'"', false);
});

it('does not warn about a page whose url is free', function () {
    Route::get('/about', fn () => 'theirs');
    Inlay::pageRoutes();
    $page = Page::create(['title' => 'Pricing', 'path' => 'pricing']);

    Livewire::test(PageEditor::class, ['page' => $page])->assertDontSee('data-test="route-conflict"', false);
});

it('reports hidden pages and routes in inlay:doctor', function () {
    Route::get('/about', fn () => 'theirs')->name('about');
    Inlay::pageRoutes();
    Route::get('/dashboard', fn () => 'dashboard')->name('dashboard');
    Page::create(['title' => 'About', 'path' => 'about']);

    $this->artisan('inlay:doctor')
        ->expectsOutputToContain('About (/about) is answered by the "about" route')
        ->expectsOutputToContain('the "dashboard" route')
        ->assertFailed();
});
