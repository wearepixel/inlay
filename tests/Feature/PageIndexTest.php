<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\Inlay\Livewire\PageIndex;
use WearePixel\Inlay\Models\Page;

it('lists pages, most recently updated first', function () {
    $older = Page::create(['title' => 'About', 'slug' => 'about']);
    $older->forceFill(['updated_at' => now()->subDay()])->save();

    Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageIndex::class)
        ->assertSeeInOrder(['Home', 'About']);
});

it('filters pages by title search', function () {
    Page::create(['title' => 'Home', 'slug' => 'home']);
    Page::create(['title' => 'Contact', 'slug' => 'contact']);

    Livewire::test(PageIndex::class)
        ->set('search', 'Contact')
        ->assertSee('Contact')
        ->assertDontSee('Home');
});

it('deletes a page', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);

    Livewire::test(PageIndex::class)
        ->call('delete', $page->id)
        ->assertOk();

    expect(Page::find($page->id))->toBeNull();
});
