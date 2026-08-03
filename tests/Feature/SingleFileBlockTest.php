<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Actions\RestorePageRevision;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Tests\Fixtures\Spike\SpikeInlineWidget;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.blocks' => []]);
    $this->fixturesPath = __DIR__.'/../Fixtures/single-file';
});

it('registers a single-file block explicitly', function () {
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");

    expect(app(BlockRegistry::class)->slugs())->toContain('single-file-hero');
});

it('discovers every *.blade.php file directly inside a directory, using each file\'s own declared handle', function () {
    Inlay::singleFileBlocks("{$this->fixturesPath}/discoverable");

    $slugs = app(BlockRegistry::class)->slugs();

    expect($slugs)->toContain('discoverable-a')->toContain('discoverable-b');
});

it('renders a single-file block through the real view engine with resolved settings', function () {
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");

    $html = app(SectionRenderer::class)->render([
        ['handle' => 'single-file-hero', 'settings' => ['heading' => 'Hello from a single file', 'showCta' => true]],
    ]);

    expect($html)
        ->toContain('Hello from a single file')
        ->toContain('single-file-hero-cta')
        ->toContain('Learn more') // ctaLabel default, since only heading/showCta were overridden
        ->toContain('large'); // imageSize default
});

it('mounts and hydrates nested Livewire inside a single-file block\'s own markup', function () {
    Livewire::component('spike-inline-widget', SpikeInlineWidget::class);
    Inlay::singleFileBlock("{$this->fixturesPath}/nested-livewire.blade.php");

    $html = app(SectionRenderer::class)->render([
        ['handle' => 'single-file-nested-livewire', 'settings' => []],
    ]);

    expect($html)->toContain('wire:snapshot');
});

it('appears in the block picker grouped by its own category, identically to a class-based block', function () {
    config(['inlay.blocks' => ['test-hero' => TestHeroSection::class]]);
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");

    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSeeHtml('Headers') // single-file-hero's category
        ->assertSeeHtml('Hero')
        ->assertSeeHtml('A single-file hero block.');
});

it('supports draft editing through the ordinary PageEditor flow with no special-casing', function () {
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");

    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'single-file-hero')
        ->assertSet('editingProps.heading', 'Welcome')
        ->set('editingProps.heading', 'Edited via the editor')
        ->assertOk();

    expect($page->sections()->sole()->settings)->toBe(['heading' => 'Edited via the editor']);
});

it('publishes a page composed of a single-file block', function () {
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");

    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'single-file-hero', 'order' => 0, 'settings' => ['heading' => 'Published heading']]);

    $revision = app(PublishPage::class)->execute($page);

    expect($page->fresh()->status)->toBe(PageStatus::Published)
        ->and($revision->snapshot['sections'][0]['settings'])->toBe([
            'heading' => 'Published heading',
            'showCta' => false,
            'ctaLabel' => 'Learn more',
            'imageSize' => 'large',
        ]);
});

it('restores a revision that includes a single-file block', function () {
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");

    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'single-file-hero', 'order' => 0, 'settings' => ['heading' => 'Original']]);
    $original = app(PublishPage::class)->execute($page);

    $page->sections()->first()->update(['settings' => ['heading' => 'Edited']]);
    app(PublishPage::class)->execute($page);

    app(RestorePageRevision::class)->execute($original);

    expect($page->sections()->first()->settings['heading'])->toBe('Original');
});

it('rejects a single-file block whose handle collides with an already-registered class-based one', function () {
    config(['inlay.blocks' => ['single-file-hero' => TestHeroSection::class]]);

    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");
})->throws(InvalidArgumentException::class, 'a class-based block is already registered for that handle');

it('rejects two single-file blocks registered under the same handle', function () {
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");
})->throws(InvalidArgumentException::class, 'a single-file block is already registered for that handle');

it('behaves no differently downstream than a class-based block with an equivalent shape', function () {
    config(['inlay.blocks' => ['test-hero' => TestHeroSection::class]]);
    Inlay::singleFileBlock("{$this->fixturesPath}/hero.blade.php");

    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $classBased = $page->sections()->create(['handle' => 'test-hero', 'order' => 0, 'settings' => ['heading' => 'Class-based']]);
    $singleFile = $page->sections()->create(['handle' => 'single-file-hero', 'order' => 1, 'settings' => ['heading' => 'Single-file']]);

    // The exact same editor flow, unaware of which authoring style produced
    // either section.
    $test = Livewire::test(PageEditor::class, ['page' => $page]);

    $test->call('selectSection', $classBased->id)->assertSet('editingProps.heading', 'Class-based');
    $test->call('selectSection', $singleFile->id)->assertSet('editingProps.heading', 'Single-file');

    $test->call('duplicateSection', $classBased->id)->assertOk();
    $test->call('duplicateSection', $singleFile->id)->assertOk();

    expect($page->sections()->count())->toBe(4);

    $revision = app(PublishPage::class)->execute($page);

    expect($revision->snapshot['sections'])->toHaveCount(4)
        ->and(collect($revision->snapshot['sections'])->pluck('handle')->all())->toBe([
            'test-hero', 'single-file-hero', 'test-hero', 'single-file-hero',
        ]);
});
