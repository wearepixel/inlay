<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('lists revisions and restores a chosen one', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
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
