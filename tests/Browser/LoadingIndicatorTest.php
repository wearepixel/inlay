<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

beforeEach(function () {
    config(['inlay.sections' => ['hero' => BrowserHeroSection::class]]);
});

it('clears the Saving indicator once the debounced setting update actually completes', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click("@tree-item-{$section->id}")
        ->clear('@setting-heading')
        ->type('@setting-heading', 'Updated heading')
        ->wait(1);

    $editor->assertMissing('.inlay-status--saving')
        ->assertNoJavaScriptErrors();
});

it('clears the Publishing indicator once publish actually completes', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->click('@publish')->wait(1);

    $editor->assertSee('Publish')
        ->assertDontSee('Publishing')
        ->assertNoJavaScriptErrors();
});
