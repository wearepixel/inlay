<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Livewire\SiteSettingsEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\SiteSetting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Tests\Fixtures\TestRichTextSection;

it('strips script from rich text saved in the page editor', function () {
    config(['inlay.blocks' => ['test-rich-text' => TestRichTextSection::class]]);
    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $section = $page->sections()->create(['handle' => 'test-rich-text', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('selectSection', $section->id)
        ->set('editingProps.body', '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(1)>');

    expect($section->fresh()->settings['body'])
        ->toContain('<p>Hello</p>')
        ->not->toContain('<script')
        ->not->toContain('onerror');
});

it('strips script from rich text saved in site settings', function () {
    Inlay::siteSettings('footer', Settings::make()->richtext('note'));

    Livewire::test(SiteSettingsEditor::class)
        ->set('editingProps.note', '<p>Hi</p><script>alert(1)</script>');

    expect(SiteSetting::where('handle', 'footer')->value('values')['note'])
        ->toContain('<p>Hi</p>')
        ->not->toContain('<script');
});
