<?php

declare(strict_types=1);

use Livewire\Livewire;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Actions\RestorePageRevision;
use WeArePixel\Inlay\Enums\PageStatus;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Livewire\PageEditor;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Tests\Fixtures\Spike\SpikeInlineWidget;
use WeArePixel\Inlay\Tests\Fixtures\TestHeroSection;

beforeEach(function () {
    config(['inlay.sections' => []]);
    $this->fixturesPath = __DIR__.'/../Fixtures/single-file';
});

it('registers a single-file section explicitly', function () {
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");

    expect(app(SectionRegistry::class)->slugs())->toContain('single-file-hero');
});

it('discovers every *.blade.php file directly inside a directory, using each file\'s own declared handle', function () {
    Inlay::singleFileSections("{$this->fixturesPath}/discoverable");

    $slugs = app(SectionRegistry::class)->slugs();

    expect($slugs)->toContain('discoverable-a')->toContain('discoverable-b');
});

it('renders a single-file section through the real view engine with resolved settings', function () {
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");

    $html = app(SectionRenderer::class)->render([
        ['handle' => 'single-file-hero', 'settings' => ['heading' => 'Hello from a single file', 'showCta' => true]],
    ]);

    expect($html)
        ->toContain('Hello from a single file')
        ->toContain('single-file-hero-cta')
        ->toContain('Learn more') // ctaLabel default, since only heading/showCta were overridden
        ->toContain('large'); // imageSize default
});

it('mounts and hydrates nested Livewire inside a single-file section\'s own markup', function () {
    Livewire::component('spike-inline-widget', SpikeInlineWidget::class);
    Inlay::singleFileSection("{$this->fixturesPath}/nested-livewire.blade.php");

    $html = app(SectionRenderer::class)->render([
        ['handle' => 'single-file-nested-livewire', 'settings' => []],
    ]);

    expect($html)->toContain('wire:snapshot');
});

it('appears in the section picker grouped by its own category, identically to a class-based section', function () {
    config(['inlay.sections' => ['test-hero' => TestHeroSection::class]]);
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");

    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('openPicker')
        ->assertSeeHtml('Headers') // single-file-hero's category
        ->assertSeeHtml('Hero')
        ->assertSeeHtml('A single-file hero section.');
});

it('supports draft editing through the ordinary PageEditor flow with no special-casing', function () {
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");

    $page = Page::create(['title' => 'Home', 'path' => 'home']);

    Livewire::test(PageEditor::class, ['page' => $page])
        ->call('addSection', 'single-file-hero')
        ->assertSet('editingProps.heading', 'Welcome')
        ->set('editingProps.heading', 'Edited via the editor')
        ->assertOk();

    expect($page->sections()->sole()->settings)->toBe(['heading' => 'Edited via the editor']);
});

it('publishes a page composed of a single-file section', function () {
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");

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

it('restores a revision that includes a single-file section', function () {
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");

    $page = Page::create(['title' => 'Home', 'path' => 'home']);
    $page->sections()->create(['handle' => 'single-file-hero', 'order' => 0, 'settings' => ['heading' => 'Original']]);
    $original = app(PublishPage::class)->execute($page);

    $page->sections()->first()->update(['settings' => ['heading' => 'Edited']]);
    app(PublishPage::class)->execute($page);

    app(RestorePageRevision::class)->execute($original);

    expect($page->sections()->first()->settings['heading'])->toBe('Original');
});

it('rejects a single-file section whose handle collides with an already-registered class-based one', function () {
    config(['inlay.sections' => ['single-file-hero' => TestHeroSection::class]]);

    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");
})->throws(InvalidArgumentException::class, 'a class-based section is already registered for that handle');

it('rejects two single-file sections registered under the same handle', function () {
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");
})->throws(InvalidArgumentException::class, 'a single-file section is already registered for that handle');

it('behaves no differently downstream than a class-based section with an equivalent shape', function () {
    config(['inlay.sections' => ['test-hero' => TestHeroSection::class]]);
    Inlay::singleFileSection("{$this->fixturesPath}/hero.blade.php");

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
