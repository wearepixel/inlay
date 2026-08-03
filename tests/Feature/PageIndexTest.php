<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Livewire\PageIndex;
use WeArePixel\Inlay\Models\Page;

it('lists pages, most recently updated first', function () {
    $older = Page::create(['title' => 'About', 'path' => 'about']);
    $older->forceFill(['updated_at' => now()->subDay()])->save();

    Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageIndex::class)
        ->assertSeeInOrder(['Home', 'About']);
});

it('filters pages by title search', function () {
    Page::create(['title' => 'Home', 'path' => 'home']);
    Page::create(['title' => 'Contact', 'path' => 'contact']);

    Livewire::test(PageIndex::class)
        ->set('search', 'Contact')
        ->assertSee('Contact')
        ->assertDontSee('Home');
});

it('deletes a page', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageIndex::class)
        ->call('delete', $page->id)
        ->assertOk();

    expect(Page::find($page->id))->toBeNull();
});

it('paginates with its own inlay-owned view, never Laravel\'s default Tailwind pagination markup', function () {
    for ($i = 1; $i <= 25; $i++) {
        Page::create(['title' => "Page {$i}", 'path' => "page-{$i}"]);
    }

    $html = Livewire::test(PageIndex::class)->html();

    expect($html)
        ->toContain('inlay-pagination')
        ->not->toContain('inline-flex items-center px-4 py-2') // Laravel's bundled Tailwind pagination view
        ->not->toContain('bg-blue-500')
        ->not->toContain('btn-primary'); // a Bootstrap-flavoured pagination view would use this instead
});
