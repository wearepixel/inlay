<?php

declare(strict_types=1);

use App\Inlay\LandingPage;
use Illuminate\Filesystem\Filesystem;
use WeArePixel\Inlay\Composition\CompositionReport;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Support\BlockRegistry;

beforeEach(function () {
    $this->base = sys_get_temp_dir().'/inlay-starter-'.bin2hex(random_bytes(4));
    (new Filesystem)->ensureDirectoryExists($this->base.'/config');
    $this->app->setBasePath($this->base);
    $this->app->useAppPath($this->base.'/app');
});

afterEach(function () {
    (new Filesystem)->deleteDirectory($this->base);
});

it('copies the landing blocks and page type, keeping existing files unless forced', function () {
    $this->artisan('inlay:install', ['--starter' => 'landing'])->assertSuccessful();

    $blocks = glob($this->base.'/resources/views/inlay/blocks/*.blade.php');
    expect($blocks)->toHaveCount(10)
        ->and($this->base.'/app/Inlay/LandingPage.php')->toBeFile();

    file_put_contents($this->base.'/resources/views/inlay/blocks/hero.blade.php', 'customised');

    $this->artisan('inlay:install', ['--starter' => 'landing'])->assertSuccessful();
    expect(file_get_contents($this->base.'/resources/views/inlay/blocks/hero.blade.php'))->toBe('customised');

    $this->artisan('inlay:install', ['--starter' => 'landing', '--force' => true])->assertSuccessful();
    expect(file_get_contents($this->base.'/resources/views/inlay/blocks/hero.blade.php'))->not->toBe('customised');
});

it('rejects an unknown starter without installing anything', function () {
    $this->artisan('inlay:install', ['--starter' => 'nope'])->assertFailed();

    expect($this->base.'/resources/views/inlay')->not->toBeDirectory();
});

it('registers every starter block and renders each one from its defaults without problems', function (string $variant) {
    Inlay::singleFileBlocks(dirname(__DIR__, 2)."/stubs/starters/landing/{$variant}/blocks");
    require_once dirname(__DIR__, 2).'/stubs/starters/landing/LandingPage.php.stub';
    Inlay::pageDefinition(LandingPage::class);

    $handles = app(BlockRegistry::class)->slugs();
    $report = app(CompositionReport::class)->build(array_map(fn (string $handle) => ['handle' => $handle], $handles));

    $failures = collect($report['sections'])
        ->flatMap(fn (array $section) => collect($section['problems'])->filter(fn (string $problem) => str_starts_with($problem, 'Failed to render')))
        ->all();

    expect($handles)->toHaveCount(10)
        ->and($failures)->toBe([])
        ->and(app(PageDefinitionRegistry::class)->definition('landing-page')?->defaultBlocks)->toBe(['hero', 'feature-grid', 'cta-band']);
})->with(['tailwind', 'plain']);

it('installs the plain variant with its stylesheet', function () {
    $this->artisan('inlay:install', ['--starter' => 'landing', '--css' => 'plain'])->assertSuccessful();

    expect(glob($this->base.'/resources/views/inlay/blocks/*.blade.php'))->toHaveCount(10)
        ->and(file_get_contents($this->base.'/resources/views/inlay/blocks/hero.blade.php'))->toContain('inlay-hero')
        ->and($this->base.'/resources/css/inlay.css')->toBeFile();
});

it('uses only classes its own stylesheet defines in the plain variant', function () {
    $defined = array_map('strval', array_keys((new WeArePixel\Inlay\Styling\CompiledStylesheets)->parseClasses((string) file_get_contents(dirname(__DIR__, 2).'/stubs/starters/landing/plain/inlay.css'))));
    $used = [];

    foreach (glob(dirname(__DIR__, 2).'/stubs/starters/landing/plain/blocks/*.blade.php') as $block) {
        preg_match_all('/inlay-[a-z0-9_-]+/', (string) file_get_contents($block), $matches);
        array_push($used, ...$matches[0]);
    }

    $dynamic = ['inlay-tone-', 'inlay-grid--'];
    $undefined = array_filter(array_unique($used), fn (string $class) => ! in_array($class, $defined, true) && ! in_array($class, $dynamic, true));

    expect(array_values($undefined))->toBe([]);
});
