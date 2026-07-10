<?php

declare(strict_types=1);

use Livewire\Livewire;
use WearePixel\BladePageBuilder\Actions\PublishPage;
use WearePixel\BladePageBuilder\Livewire\PageEditor;
use WearePixel\BladePageBuilder\Models\Page;
use WearePixel\BladePageBuilder\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['blade-page-builder.sections' => [
        'test-hero' => TestHeroSection::class,
    ]]);
});

it('lists revisions and restores a chosen one', function () {
    $page = Page::create(['title' => 'Home', 'slug' => 'home']);
    $page->sections()->create(['section_type' => 'test-hero', 'order' => 0, 'props' => ['heading' => 'Original', 'layout' => 'image-right']]);
    $original = (new PublishPage)->execute($page);

    $page->sections()->first()->update(['props' => ['heading' => 'Edited', 'layout' => 'image-right']]);
    (new PublishPage)->execute($page);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('toggleHistory')
        ->call('restore', $original->id)
        ->assertOk()
        ->call('toggleHistory')
        ->assertSee('Restored from revision');

    expect($page->sections()->first()->props['heading'])->toBe('Original');
});
