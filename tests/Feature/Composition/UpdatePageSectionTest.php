<?php

declare(strict_types=1);

use WeArePixel\Inlay\Actions\UpdatePageSection;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;

beforeEach(function () {
    Inlay::singleFileSection(__DIR__.'/../../Fixtures/single-file/composition/promo.blade.php');
});

it('merges a settings patch into the stored overrides, and null resets a field to its default', function () {
    $page = Page::create(['title' => 'Landing']);
    $section = $page->sections()->create(['handle' => 'promo', 'order' => 0, 'settings' => ['heading' => 'Old', 'dark' => true]]);

    app(UpdatePageSection::class)->execute($page, $section->id, ['heading' => 'New', 'dark' => null, 'columns' => 2]);

    expect($section->fresh()->settings)->toBe(['heading' => 'New', 'columns' => 2]);
});

it('rejects an invalid patch and keeps the stored settings', function () {
    $page = Page::create(['title' => 'Landing']);
    $section = $page->sections()->create(['handle' => 'promo', 'order' => 0, 'settings' => ['heading' => 'Old']]);

    expect(fn () => app(UpdatePageSection::class)->execute($page, $section->id, ['heading' => '', 'colour' => 'red']))
        ->toThrow(InvalidCompositionException::class);

    expect($section->fresh()->settings)->toBe(['heading' => 'Old']);
});

it('cannot update a section that belongs to another page', function () {
    $page = Page::create(['title' => 'Landing']);
    $other = Page::create(['title' => 'Other']);
    $section = $other->sections()->create(['handle' => 'promo', 'order' => 0, 'settings' => ['heading' => 'Theirs']]);

    expect(fn () => app(UpdatePageSection::class)->execute($page, $section->id, ['heading' => 'Mine']))
        ->toThrow(InvalidCompositionException::class);

    expect($section->fresh()->settings)->toBe(['heading' => 'Theirs']);
});

it('sanitises rich text in a patch, including inside nested items', function () {
    $page = Page::create(['title' => 'Landing']);
    $section = $page->sections()->create(['handle' => 'promo', 'order' => 0, 'settings' => []]);

    app(UpdatePageSection::class)->execute($page, $section->id, [
        'body' => '<p>Ok</p><script>alert(1)</script>',
        'items' => [['title' => 'One', 'detail' => '<em>Fine</em><img src=x onerror="alert(1)">']],
    ]);

    $settings = $section->fresh()->settings;

    expect($settings['body'])->toBe('<p>Ok</p>')
        ->and($settings['items'][0]['detail'])->toBe('<em>Fine</em>');
});
