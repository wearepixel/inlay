<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\Inlay\Actions\PublishPage;
use WearePixel\Inlay\Livewire\PageEditor;
use WearePixel\Inlay\Models\Page;
use WearePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('lists revisions and restores a chosen one', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Original', 'layout' => 'image-right']]);
    $original = app(PublishPage::class)->execute($page);

    $page->sections()->first()->update(['settings' => ['heading' => 'Edited', 'layout' => 'image-right']]);
    app(PublishPage::class)->execute($page);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('toggleHistory')
        ->call('restore', $original->id)
        ->assertOk()
        ->call('toggleHistory')
        ->assertSee('Restored from revision');

    expect($page->sections()->first()->settings['heading'])->toBe('Original');
});
