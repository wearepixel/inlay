<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Livewire\PageCreate;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Tests\Fixtures\TestCollectionPageDefinition;
use WeArePixel\Inlay\Tests\Fixtures\TestFaqSection;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

it('creates a draft page with no public path by default', function () {
    $component = Livewire::test(PageCreate::class)
        ->set('title', 'About Us')
        ->call('save');

    $page = Page::sole();

    $component->assertRedirect(route('inlay.admin.pages.edit', $page));

    expect($page->title)->toBe('About Us')
        ->and($page->path)->toBeNull()
        ->and($page->is_homepage)->toBeFalse()
        ->and($page->status)->toBe(PageStatus::Draft);
});

it('normalises a hand-entered public path before storing it', function () {
    Livewire::test(PageCreate::class)
        ->set('title', 'Test Page')
        ->set('path', '/test/test/')
        ->call('save');

    expect(Page::sole()->path)->toBe('test/test');
});

it('requires a title', function () {
    Livewire::test(PageCreate::class)
        ->set('path', 'no-title')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

it('rejects a path already used by another page', function () {
    Page::create(['title' => 'Existing', 'path' => 'existing']);

    Livewire::test(PageCreate::class)
        ->set('title', 'New page')
        ->set('path', 'existing')
        ->call('save')
        ->assertHasErrors(['path']);

    expect(Page::count())->toBe(1);
});

it('rejects a path already used by another page even in a different slash form', function () {
    Page::create(['title' => 'Existing', 'path' => 'existing']);

    Livewire::test(PageCreate::class)
        ->set('title', 'New page')
        ->set('path', '/existing/')
        ->call('save')
        ->assertHasErrors(['path']);

    expect(Page::count())->toBe(1);
});

it('rejects a pasted full URL as a public path', function () {
    Livewire::test(PageCreate::class)
        ->set('title', 'New page')
        ->set('path', 'https://example.com/about')
        ->call('save')
        ->assertHasErrors(['path']);

    expect(Page::count())->toBe(0);
});

it('rejects a path containing a query string', function () {
    Livewire::test(PageCreate::class)
        ->set('title', 'New page')
        ->set('path', 'about?ref=1')
        ->call('save')
        ->assertHasErrors(['path']);

    expect(Page::count())->toBe(0);
});

it('sets the new page as the homepage and unsets any previous one', function () {
    $previousHomepage = Page::create(['title' => 'Old Home', 'is_homepage' => true]);

    Livewire::test(PageCreate::class)
        ->set('title', 'New Home')
        ->set('isHomepage', true)
        ->call('save');

    expect($previousHomepage->fresh()->is_homepage)->toBeFalse()
        ->and(Page::where('title', 'New Home')->sole()->is_homepage)->toBeTrue();
});

it('stores optional meta title and meta description', function () {
    Livewire::test(PageCreate::class)
        ->set('title', 'About Us')
        ->set('metaTitle', 'About the team')
        ->set('metaDescription', 'Learn more about us.')
        ->call('save');

    $page = Page::sole();

    expect($page->meta_title)->toBe('About the team')
        ->and($page->meta_description)->toBe('Learn more about us.');
});

it('leaves meta title and meta description null when left blank', function () {
    Livewire::test(PageCreate::class)
        ->set('title', 'About Us')
        ->call('save');

    $page = Page::sole();

    expect($page->meta_title)->toBeNull()
        ->and($page->meta_description)->toBeNull();
});

it('assigns the built-in default definition and hides the selector when nothing else is registered', function () {
    Livewire::test(PageCreate::class)
        ->assertDontSeeHtml('page-definition')
        ->set('title', 'About Us')
        ->call('save');

    expect(Page::sole()->definition)->toBe('default');
});

it('shows the Page type selector once a second definition is registered, and seeds its default sections in order', function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
        'test-faq' => TestFaqSection::class,
    ]]);
    app(PageDefinitionRegistry::class)->register(TestCollectionPageDefinition::class);

    Livewire::test(PageCreate::class)
        ->assertSeeHtml('page-definition')
        ->set('title', 'Landing Page')
        ->set('definitionHandle', 'test-collection-page-definition')
        ->call('save');

    $page = Page::sole();

    expect($page->definition)->toBe('test-collection-page-definition')
        ->and($page->sections()->orderBy('order')->pluck('handle')->all())->toBe(['test-hero']);
});
