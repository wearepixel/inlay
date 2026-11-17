<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Actions\ReplacePageComposition;
use WeArePixel\Inlay\Composition\InvalidCompositionException;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Schema\Settings;

beforeEach(function () {
    Inlay::singleFileBlocks(__DIR__.'/../Fixtures/single-file/blocks');
    Inlay::singleFileSections(__DIR__.'/../Fixtures/single-file/block-sections');

    $this->page = Page::create(['title' => 'Home', 'path' => 'home']);
});

function stackSection(Page $page, array $content): PageSection
{
    return $page->sections()->create(['handle' => 'stack', 'order' => 0, 'settings' => ['content' => $content]]);
}

it('renders a section\'s blocks in order through {{ $content }}', function () {
    stackSection($this->page, [
        ['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'First']],
        ['id' => 'b', 'type' => 'text', 'settings' => ['body' => '<p>Second</p>']],
    ]);
    app(PublishPage::class)->execute($this->page);

    $html = Inlay::renderPage($this->page->fresh())->toHtml();

    expect(preg_replace('/\s*\n\s*/', '', $html))->toContain('<section class="stack"><h2>First</h2><div class="text"><p>Second</p></div></section>');
});

it('renders a section\'s default blocks until an editor changes them', function () {
    $this->page->sections()->create(['handle' => 'stack', 'order' => 0, 'settings' => []]);
    app(PublishPage::class)->execute($this->page);

    expect(Inlay::renderPage($this->page->fresh())->toHtml())->toContain('<h2>Default heading</h2>');
});

it('skips a block whose type is no longer registered rather than failing the section', function () {
    stackSection($this->page, [
        ['id' => 'a', 'type' => 'retired', 'settings' => []],
        ['id' => 'b', 'type' => 'heading', 'settings' => ['text' => 'Still here']],
    ]);
    app(PublishPage::class)->execute($this->page);

    expect(Inlay::renderPage($this->page->fresh())->toHtml())->toContain('<h2>Still here</h2>');
});

it('adds an allowed block with its defaults and opens it', function () {
    $section = stackSection($this->page, []);

    $editor = Livewire::test(PageEditor::class, ['page' => $this->page])
        ->call('selectSection', $section->id)
        ->call('addBlock', 'content', 'text');

    $stored = $section->fresh()->settings['content'];

    expect($stored)->toHaveCount(1)
        ->and($stored[0]['type'])->toBe('text')
        ->and($stored[0]['settings']['body'])->toBe('<p>Some text</p>')
        ->and($stored[0]['id'])->toMatch('/^[a-z0-9]{26}$/');
    $editor->assertSet('selectedBlockId', $stored[0]['id'])->assertSee('data-test="block-inspector"', false);
});

it('refuses a block type the area does not allow', function () {
    $section = stackSection($this->page, []);

    Livewire::test(PageEditor::class, ['page' => $this->page])
        ->call('selectSection', $section->id)
        ->call('addBlock', 'content', 'button')
        ->call('addBlock', 'eyebrow', 'heading');

    expect($section->fresh()->settings['content'])->toBe([]);
});

it('stops adding at the area\'s maximum', function () {
    $section = stackSection($this->page, []);
    $editor = Livewire::test(PageEditor::class, ['page' => $this->page])->call('selectSection', $section->id);

    foreach (range(1, 5) as $ignored) {
        $editor->call('addBlock', 'content', 'heading');
    }

    expect($section->fresh()->settings['content'])->toHaveCount(3);
});

it('saves an edit to a block\'s field into that block', function () {
    $section = stackSection($this->page, [
        ['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'One']],
        ['id' => 'b', 'type' => 'heading', 'settings' => ['text' => 'Two']],
    ]);

    Livewire::test(PageEditor::class, ['page' => $this->page])
        ->call('selectBlock', $section->id, 'b')
        ->set('editingProps.content.1.settings.text', 'Changed');

    expect(array_column(array_column($section->fresh()->settings['content'], 'settings'), 'text'))->toBe(['One', 'Changed']);
});

it('moves, duplicates and removes blocks', function () {
    $section = stackSection($this->page, [
        ['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'One']],
        ['id' => 'b', 'type' => 'heading', 'settings' => ['text' => 'Two']],
    ]);
    $editor = Livewire::test(PageEditor::class, ['page' => $this->page])->call('selectSection', $section->id);

    $editor->call('moveBlock', 'content', 'b', -1);
    expect(array_column($section->fresh()->settings['content'], 'id'))->toBe(['b', 'a']);

    $editor->call('duplicateBlock', 'content', 'a');
    $content = $section->fresh()->settings['content'];
    expect($content)->toHaveCount(3)
        ->and($content[2]['settings']['text'])->toBe('One')
        ->and($content[2]['id'])->not->toBe('a');

    $editor->call('removeBlock', 'content', 'b');
    expect(array_column($section->fresh()->settings['content'], 'id'))->toBe(['a', $content[2]['id']]);
});

it('drops disallowed types and undeclared keys a client writes into the blocks area', function () {
    $section = stackSection($this->page, [['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'One']]]);

    Livewire::test(PageEditor::class, ['page' => $this->page])
        ->call('selectSection', $section->id)
        ->set('editingProps.content', [
            ['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'Kept', 'injected' => 'x']],
            ['id' => 'b', 'type' => 'button', 'settings' => []],
            ['id' => '../../evil', 'type' => 'text', 'settings' => []],
        ]);

    $content = $section->fresh()->settings['content'];

    expect($content)->toHaveCount(2)
        ->and($content[0]['settings'])->toBe(['text' => 'Kept'])
        ->and($content[1]['type'])->toBe('text')
        ->and($content[1]['id'])->not->toBe('../../evil');
});

it('strips script from rich text inside a block', function () {
    $section = stackSection($this->page, [['id' => 'a', 'type' => 'text', 'settings' => ['body' => '<p>Hi</p>']]]);

    Livewire::test(PageEditor::class, ['page' => $this->page])
        ->call('selectBlock', $section->id, 'a')
        ->set('editingProps.content.0.settings.body', '<p>Hi</p><script>alert(1)</script>');

    expect($section->fresh()->settings['content'][0]['settings']['body'])
        ->toContain('<p>Hi</p>')
        ->not->toContain('<script');
});

it('tags each block in the editor preview so it can be selected', function () {
    stackSection($this->page, [['id' => 'abc', 'type' => 'heading', 'settings' => ['text' => 'One']]]);

    $this->get(route('inlay.admin.pages.preview', $this->page))
        ->assertOk()
        ->assertSee('data-inlay-block="abc"', false)
        ->assertSee('data-inlay-block-label="Heading"', false);
});

it('validates blocks in a composition and stores them with ids', function () {
    $compose = app(ReplacePageComposition::class);

    expect(fn () => $compose->execute($this->page, [['section' => 'stack', 'settings' => ['content' => [
        ['type' => 'button', 'settings' => []],
    ]]]]))->toThrow(InvalidCompositionException::class, 'sections.0.settings.content.0.type: Must be one of: heading, text.');

    expect(fn () => $compose->execute($this->page, [['section' => 'stack', 'settings' => ['content' => [
        ['type' => 'heading', 'settings' => ['colour' => 'red']],
    ]]]]))->toThrow(InvalidCompositionException::class, 'sections.0.settings.content.0.settings.colour: Unknown setting.');

    $compose->execute($this->page, [['section' => 'stack', 'settings' => ['content' => [
        ['type' => 'heading', 'settings' => ['text' => 'From an agent']],
    ]]]]);

    $stored = $this->page->sections()->sole()->settings['content'][0];

    expect($stored['settings'])->toBe(['text' => 'From an agent'])
        ->and($stored['id'])->toMatch('/^[a-z0-9]{26}$/');
});

it('refuses a block that declares its own blocks area', function () {
    Inlay::singleFileBlock(__DIR__.'/../Fixtures/single-file/invalid-blocks/nested.blade.php');
})->throws(InvalidArgumentException::class, 'blocks cannot contain blocks');

it('refuses a blocks area in site settings', function () {
    Inlay::siteSettings('footer', Settings::make()->blocks('content'));
})->throws(InvalidArgumentException::class, 'blocks belong in a page section');

it('refuses a blocks area nested inside a repeater', function () {
    Settings::make()->repeater('items', Settings::make()->blocks('content'));
})->throws(InvalidArgumentException::class, 'cannot contain a blocks() field');

it('caps a blocks area with no max() at the shared item limit', function () {
    Inlay::singleFileSections(__DIR__.'/../Fixtures/single-file/unbounded-blocks');
    $section = $this->page->sections()->create(['handle' => 'open', 'order' => 0, 'settings' => []]);

    Livewire::test(PageEditor::class, ['page' => $this->page])
        ->call('selectSection', $section->id)
        ->set('editingProps.content', array_fill(0, Settings::MAX_ITEMS + 50, ['type' => 'heading', 'settings' => []]));

    expect($section->fresh()->settings['content'])->toHaveCount(Settings::MAX_ITEMS);
});

it('tags a block\'s own root element in the preview rather than wrapping it', function () {
    stackSection($this->page, [['id' => 'abc', 'type' => 'heading', 'settings' => ['text' => 'One']]]);

    $this->get(route('inlay.admin.pages.preview', $this->page))
        ->assertSee('<h2 data-inlay-block="abc"', false);
});
