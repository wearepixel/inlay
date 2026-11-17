<?php

declare(strict_types=1);

use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;

beforeEach(function () {
    Inlay::singleFileBlocks(__DIR__.'/../Fixtures/single-file/blocks');
    Inlay::singleFileSections(__DIR__.'/../Fixtures/single-file/block-sections');
});

it('opens a section\'s settings when it is clicked in the preview', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'stack', 'order' => 0, 'settings' => ['eyebrow' => 'Hello', 'content' => []]]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->withinFrame('[data-test="preview-frame"]', function ($frame): void {
        $frame->script("document.querySelector('.stack').click()");
    });

    $editor->assertVisible('@setting-eyebrow')->assertValue('@setting-eyebrow', 'Hello');
});

it('opens a block\'s settings when it is clicked in the preview', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'stack', 'order' => 0, 'settings' => ['content' => [
        ['id' => 'first', 'type' => 'heading', 'settings' => ['text' => 'First heading']],
        ['id' => 'second', 'type' => 'heading', 'settings' => ['text' => 'Second heading']],
    ]]]);

    $editor = visit("/inlay/pages/{$page->id}/edit");

    $editor->withinFrame('[data-test="preview-frame"]', function ($frame): void {
        $frame->script("[...document.querySelectorAll('h2')].find((h) => h.textContent === 'Second heading').click()");
    });

    $editor->assertVisible('@block-inspector')
        ->assertValue('@setting-text', 'Second heading')
        ->click('@back-to-section')
        ->assertVisible('@blocks-content');
});
