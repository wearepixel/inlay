<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Support\LinkValue;
use WeArePixel\Inlay\Support\SectionRegistry;

beforeEach(function () {
    $this->sections = resource_path('views/inlay/sections');
    $this->source = __DIR__.'/../Fixtures/import/about.blade.php';
});

afterEach(fn () => (new Filesystem)->deleteDirectory(resource_path('views/inlay')));

it('turns each top-level element into a section and builds a draft page from them', function () {
    $this->artisan('inlay:import', ['view' => $this->source, '--path' => 'about'])->assertSuccessful();

    $page = Page::query()->where('path', 'about')->sole();

    expect($page->sections()->pluck('handle')->all())->toBe(['about-hero', 'about-values'])
        ->and("{$this->sections}/about-hero.blade.php")->toBeFile()
        ->and($page->status->value)->toBe('draft');
});

it('lifts headings, paragraphs, links and images into settings with their current content as defaults', function () {
    $this->artisan('inlay:import', ['view' => $this->source])->assertSuccessful();

    $hero = app(SectionRegistry::class)->definition('about-hero');
    $defaults = $hero->defaults();

    expect($defaults['heading'])->toBe('We help teams ship faster')
        ->and($defaults['text'])->toBe('Our tools take care of the busywork.')
        ->and($defaults['link'])->toBeInstanceOf(LinkValue::class)
        ->and($defaults['link']->url)->toBe('/contact')
        ->and($defaults['image'])->toBe('/images/team.jpg')
        ->and($defaults['imageAlt'])->toBe('The team at work');

    $html = Inlay::renderPage(tap(Page::query()->sole(), fn (Page $page) => app(PublishPage::class)->execute($page)))->toHtml();

    expect($html)
        ->toContain('<h1 class="text-5xl font-bold">We help teams ship faster</h1>')
        ->toContain('src="/images/team.jpg"')
        ->toContain('href="/contact"');
});

it('keeps inline formatting as rich text and leaves Blade expressions alone', function () {
    $this->artisan('inlay:import', ['view' => $this->source])->assertSuccessful();

    $values = app(SectionRegistry::class)->definition('about-values');
    $file = file_get_contents("{$this->sections}/about-values.blade.php");

    expect($values->settings->field('text')?->type->value)->toBe('richtext')
        ->and($values->defaults()['text'])->toContain('<strong>honest</strong>')
        ->and($file)->toContain('Signed up: {{ $count }} people');
});

it('writes nothing on a dry run', function () {
    $this->artisan('inlay:import', ['view' => $this->source, '--dry-run' => true])
        ->expectsOutputToContain('about-hero')
        ->assertSuccessful();

    expect($this->sections)->not->toBeDirectory()
        ->and(Page::query()->count())->toBe(0);
});

it('gives sections new handles when importing again rather than reusing existing files', function () {
    $this->artisan('inlay:import', ['view' => $this->source])->assertSuccessful();
    $this->artisan('inlay:import', ['view' => $this->source])->assertSuccessful();

    expect("{$this->sections}/about-hero-2.blade.php")->toBeFile();
});

it('ignores tags inside comments and scripts, and warns about Blade between elements', function () {
    $file = sys_get_temp_dir().'/inlay-import-'.bin2hex(random_bytes(3)).'.blade.php';
    file_put_contents($file, <<<'BLADE'
{{-- <div class="old"> --}}
<!-- <section> -->
<section id="one"><h2>One</h2><script>el.innerHTML = '<div>';</script></section>
@if ($show)
<section id="two"><h2>Two</h2></section>
@endif
BLADE);

    $this->artisan('inlay:import', ['view' => $file, '--dry-run' => true])
        ->expectsOutputToContain('-one')
        ->expectsOutputToContain('-two')
        ->expectsOutputToContain('@if ($show)')
        ->assertSuccessful();

    unlink($file);
});
