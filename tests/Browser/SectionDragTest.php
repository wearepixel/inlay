<?php

declare(strict_types=1);

use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Tests\Fixtures\BrowserHeroSection;

/*
 * The drag gesture itself can't be driven reliably over CDP, so these
 * dispatch the same DragEvents a browser fires and check what the editor
 * does with them.
 */
beforeEach(function () {
    config(['inlay.sections' => ['hero' => BrowserHeroSection::class]]);
});

function dragSectionAbove(int $draggedId, int $targetId, bool $drop): string
{
    $dropEvents = $drop ? "list.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer }));" : '';

    return <<<JS
        const list = document.querySelector('.inlay-tree');
        const dragged = document.querySelector('[data-section-id="{$draggedId}"]');
        const target = document.querySelector('[data-section-id="{$targetId}"]');
        const dataTransfer = new DataTransfer();
        const targetTop = target.getBoundingClientRect().top + 1;

        dragged.dispatchEvent(new DragEvent('dragstart', { bubbles: true, dataTransfer }));
        target.dispatchEvent(new DragEvent('dragover', { bubbles: true, cancelable: true, clientY: targetTop, dataTransfer }));
        {$dropEvents}
        dragged.dispatchEvent(new DragEvent('dragend', { bubbles: true, dataTransfer }));
    JS;
}

function sectionRowOrder($editor): array
{
    return $editor->script("Array.from(document.querySelectorAll('.inlay-tree [data-section-id]')).map((row) => Number(row.dataset.sectionId))");
}

it('moves a dragged section into the slot it is dropped on and saves the order', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $first = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);
    $second = $page->sections()->create(['handle' => 'hero', 'order' => 1, 'settings' => []]);
    $third = $page->sections()->create(['handle' => 'hero', 'order' => 2, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");
    $editor->script(dragSectionAbove($third->id, $first->id, drop: true));
    $editor->wait(1);

    expect($page->sections()->orderBy('order')->pluck('id')->all())->toBe([$third->id, $first->id, $second->id])
        ->and(sectionRowOrder($editor))->toBe([$third->id, $first->id, $second->id]);
});

it('puts the rows back when a drag is cancelled', function () {
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $first = $page->sections()->create(['handle' => 'hero', 'order' => 0, 'settings' => []]);
    $second = $page->sections()->create(['handle' => 'hero', 'order' => 1, 'settings' => []]);

    $editor = visit("/inlay/pages/{$page->id}/edit");
    $editor->script(dragSectionAbove($second->id, $first->id, drop: false));
    $editor->wait(1);

    expect(sectionRowOrder($editor))->toBe([$first->id, $second->id])
        ->and($page->sections()->orderBy('order')->pluck('id')->all())->toBe([$first->id, $second->id]);
});
