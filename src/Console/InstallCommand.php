<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

final class InstallCommand extends Command
{
    protected $signature = 'inlay:install
        {--starter= : Copy a starter section set into the application (available: landing)}
        {--css= : Starter styling: tailwind or plain (defaults from inlay.styling.framework)}
        {--force : Overwrite starter files that already exist}';

    protected $description = "Publish Inlay's configuration, run its migrations and optionally install a starter section set";

    private const STARTERS = ['landing'];

    private const CSS_VARIANTS = ['tailwind', 'plain'];

    public function handle(Filesystem $files): int
    {
        $starter = $this->option('starter');

        if ($starter !== null && ! in_array($starter, self::STARTERS, true)) {
            $this->components->error("Unknown starter [{$starter}]. Available: ".implode(', ', self::STARTERS).'.');

            return self::FAILURE;
        }

        $css = $this->option('css') ?? (config('inlay.styling.framework') === 'tailwind' ? 'tailwind' : 'plain');

        if ($starter !== null && ! in_array($css, self::CSS_VARIANTS, true)) {
            $this->components->error("Unknown --css [{$css}]. Available: ".implode(', ', self::CSS_VARIANTS).'.');

            return self::FAILURE;
        }

        $this->components->info('Installing Inlay...');

        $this->call('vendor:publish', ['--tag' => 'inlay-config']);
        $this->call('migrate');

        if ($starter !== null) {
            $this->installStarter($files, $starter, (string) $css);
        }

        $this->newLine();
        $this->components->info('Inlay is installed. Next steps:');
        $this->components->bulletList([
            'Call Inlay::adminRoutes() from your own routes/web.php to register the admin.',
            "Set config('inlay.admin.middleware') to guard it - the default ['web'] alone leaves it public.",
            $starter !== null
                ? 'Register the starter sections and page type in a service provider boot() method (see above).'
                : "Register your first section in config('inlay.sections').",
            'Call Inlay::previewView() so the preview iframe renders using your real frontend.',
            'Call Inlay::pageRoutes() if you want Inlay to serve published Pages publicly - see https://wearepixel.com.au/docs/inlay/v1/routing.',
        ]);

        return self::SUCCESS;
    }

    private function installStarter(Filesystem $files, string $starter, string $css): void
    {
        $root = dirname(__DIR__, 2)."/stubs/starters/{$starter}";
        $source = "{$root}/{$css}";
        $sectionsTarget = resource_path('views/inlay/sections');

        $copies = [];

        foreach ($files->glob("{$source}/sections/*.blade.php") as $sectionType) {
            $copies[$sectionType] = $sectionsTarget.'/'.basename($sectionType);
        }

        $copies["{$root}/LandingPage.php.stub"] = app_path('Inlay/LandingPage.php');

        if ($files->exists("{$source}/inlay.css")) {
            $copies["{$source}/inlay.css"] = resource_path('css/inlay.css');
        }

        $skipped = [];

        foreach ($copies as $from => $to) {
            if ($files->exists($to) && ! $this->option('force')) {
                $skipped[] = $to;

                continue;
            }

            $files->ensureDirectoryExists(dirname($to));
            $files->copy($from, $to);
        }

        $this->components->info('Copied the '.$starter.' starter: '.(count($copies) - count($skipped)).' files.');

        if ($skipped !== []) {
            $this->components->warn('Kept '.count($skipped).' existing files. Re-run with --force to overwrite them.');
        }

        $this->components->twoColumnDetail('Add to a service provider boot() method', '');
        $this->line('    Inlay::singleFileSections(resource_path(\'views/inlay/sections\'));');
        $this->line('    Inlay::pageDefinition(\App\Inlay\LandingPage::class);');
        $this->newLine();
        $this->line($css === 'tailwind'
            ? '  The sections use Tailwind v4 utility classes. Restyle them to your brand - they are yours now.'
            : '  Import resources/css/inlay.css into your main stylesheet (@import "./inlay.css";) and rebrand it with the --inlay-* variables.');
    }
}
