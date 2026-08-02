<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Livewire\PageCreate;
use WeArePixel\Inlay\Models\Page;

it('creates a draft page and redirects to its editor', function () {
    $component = Livewire::test(PageCreate::class)
        ->set('title', 'About Us')
        ->call('save');

    $page = Page::sole();

    $component->assertRedirect(route('inlay.pages.edit', $page));

    expect($page->title)->toBe('About Us')
        ->and($page->slug)->toBe('about-us')
        ->and($page->status)->toBe(PageStatus::Draft);
});

it('auto-derives the slug from the title until the slug has been edited by hand', function () {
    Livewire::test(PageCreate::class)
        ->set('title', 'Contact Page')
        ->assertSet('slug', 'contact-page')
        ->set('slug', 'custom-slug')
        ->set('title', 'Something Else Entirely')
        ->assertSet('slug', 'custom-slug');
});

it('requires a title', function () {
    Livewire::test(PageCreate::class)
        ->set('slug', 'no-title')
        ->call('save')
        ->assertHasErrors(['title' => 'required']);
});

it('rejects a slug already used by another page', function () {
    Page::create(['title' => 'Existing', 'slug' => 'existing']);

    Livewire::test(PageCreate::class)
        ->set('title', 'New page')
        ->set('slug', 'existing')
        ->call('save')
        ->assertHasErrors(['slug']);

    expect(Page::count())->toBe(1);
});
