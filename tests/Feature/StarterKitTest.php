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

it('registers every starter block and renders each one from its defaults without problems', function () {
    Inlay::singleFileBlocks(dirname(__DIR__, 2).'/stubs/starters/landing/blocks');
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
});
