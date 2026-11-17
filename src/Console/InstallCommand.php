<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use WeArePixel\Inlay\Actions\CreatePage;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Console\Install\HostWiring;
use WeArePixel\Inlay\Console\Install\Stack;
use WeArePixel\Inlay\Console\Install\StackDetector;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinition;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

final class InstallCommand extends Command
{
    protected $signature = 'inlay:install
        {--starter= : Copy a starter kit (landing, or none)}
        {--css= : Starter styling: tailwind, bootstrap or plain (detected from package.json)}
        {--layout= : Render pages inside an existing layout, e.g. component:layouts.site or extends:layouts.app}
        {--demo : Create and publish a homepage from the starter kit}
        {--force : Overwrite starter files that already exist}';

    protected $description = 'Install Inlay: detect the stack, wire up routes, views and the admin, and optionally add a starter kit';

    private const STARTERS = ['landing'];

    private const CSS_VARIANTS = ['tailwind', 'bootstrap', 'plain'];

    /** @var array<string, array{class: string, handle: string}> */
    private const STARTER_PAGE_TYPES = [
        'landing' => ['class' => 'App\\Inlay\\LandingPage', 'handle' => 'landing-page'],
    ];

    public function handle(Filesystem $files, StackDetector $detector, HostWiring $wiring): int
    {
        $stack = $detector->detect();
        $this->describe($stack);

        $starter = $this->chooseStarter($stack);

        if ($starter === false) {
            return self::FAILURE;
        }

        $css = $this->chooseCss($stack);

        if ($css === null) {
            return self::FAILURE;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'inlay-config']);
        $this->report('config/inlay.php', true);
        $this->callSilently('migrate', ['--force' => true]);
        $this->report('Database tables', true);

        if ($starter !== null) {
            $this->installStarter($files, $starter, $css);
        }

        $this->wireUp($wiring, $stack, $starter, $css);

        if ($starter !== null && $this->shouldCreateDemo($stack)) {
            $this->createDemoHomepage($starter);
        }

        $this->printNextSteps($stack);

        return self::SUCCESS;
    }

    private function describe(Stack $stack): void
    {
        $this->components->info('Installing Inlay');
        $this->components->twoColumnDetail('CSS', $stack->describeCss());
        $this->components->twoColumnDetail('Livewire (needed for the admin)', $stack->hasLivewire ? 'yes' : '<fg=yellow>no - run composer require livewire/livewire</>');
        $this->components->twoColumnDetail('Login', $stack->hasAuth ? 'yes - the admin will require it' : 'none found');
        $this->components->twoColumnDetail('Existing layouts', $stack->layouts === [] ? 'none' : implode(', ', $stack->layouts));

        if ($stack->hasFilament) {
            $this->components->twoColumnDetail('Filament', 'yes - Inlay runs alongside it at its own URL');
        }

        $this->newLine();
    }

    /**
     * @return string|null|false a starter, null for none, or false when the option names an unknown one
     */
    private function chooseStarter(Stack $stack): string|null|false
    {
        $option = $this->option('starter');

        if ($option === 'none') {
            return null;
        }

        if ($option !== null) {
            if (! in_array($option, self::STARTERS, true)) {
                $this->components->error("Unknown starter [{$option}]. Available: ".implode(', ', self::STARTERS).'.');

                return false;
            }

            return $option;
        }

        $choice = select(
            label: 'Start with a starter kit of sections and blocks?',
            options: ['landing' => 'Landing kit - hero, features, pricing, FAQ and more', 'none' => 'No, I will build my own'],
            default: $stack->isFreshApp ? 'landing' : 'none',
        );

        return $choice === 'none' ? null : $choice;
    }

    private function chooseCss(Stack $stack): ?string
    {
        $css = $this->option('css') ?? $stack->css;

        if (! in_array($css, self::CSS_VARIANTS, true)) {
            $this->components->error("Unknown --css [{$css}]. Available: ".implode(', ', self::CSS_VARIANTS).'.');

            return null;
        }

        return $css;
    }

    private function chooseLayout(Stack $stack): ?string
    {
        if ($this->option('layout') !== null) {
            return $this->option('layout');
        }

        if ($stack->layouts === []) {
            return null;
        }

        $options = ['standalone' => 'A standalone page Inlay creates (you can change it later)'];

        foreach ($stack->layouts as $layout) {
            $options[$layout] = 'Inside '.substr($layout, strpos($layout, ':') + 1);
        }

        $choice = select(label: 'Which layout should published pages render inside?', options: $options, default: 'standalone');

        return $choice === 'standalone' ? null : $choice;
    }

    private function wireUp(HostWiring $wiring, Stack $stack, ?string $starter, string $css): void
    {
        $layout = $this->chooseLayout($stack);
        $pageDefinitions = $starter !== null ? [self::STARTER_PAGE_TYPES[$starter]['class']] : [];

        $this->report('Admin guard in config/inlay.php', $wiring->guardAdmin(requireLogin: $stack->hasAuth));
        $this->report('app/Providers/InlayServiceProvider.php', $wiring->writeServiceProvider($pageDefinitions));
        $this->report('resources/views/inlay/pages/show.blade.php', $wiring->writePageView($layout, $stack->viteEntries));
        $this->report('Routes in routes/web.php', $wiring->writeRoutes(replaceWelcomeRoute: $stack->isFreshApp));
        $this->report('INLAY_CSS_FRAMEWORK in .env', $wiring->setCssFramework($css));
    }

    private function report(string $what, bool $changed): void
    {
        $this->components->twoColumnDetail($what, $changed ? '<fg=green;options=bold>DONE</>' : '<fg=gray>already set up</>');
    }

    private function shouldCreateDemo(Stack $stack): bool
    {
        if (Page::query()->exists()) {
            return false;
        }

        if ($this->option('demo')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        return confirm(label: 'Create and publish a homepage from the starter kit?', default: $stack->isFreshApp);
    }

    /**
     * The generated service provider is not loaded in this process, so the
     * starter's sections and page type are registered here before the page
     * is built from them.
     */
    private function createDemoHomepage(string $starter): void
    {
        Inlay::singleFileBlocks(resource_path('views/inlay/blocks'));
        Inlay::singleFileSections(resource_path('views/inlay/sections'));

        ['class' => $class, 'handle' => $handle] = self::STARTER_PAGE_TYPES[$starter];

        if (! class_exists($class)) {
            require_once app_path('Inlay/'.class_basename($class).'.php');
        }

        if (! is_a($class, PageDefinition::class, true)) {
            return;
        }

        Inlay::pageDefinition($class);
        $definition = app(PageDefinitionRegistry::class)->definition($handle) ?? app(PageDefinitionRegistry::class)->default();

        $page = app(CreatePage::class)->execute(definition: $definition, title: 'Home', isHomepage: true);
        app(PublishPage::class)->execute($page, label: 'Created by inlay:install');

        $this->report("Homepage built from the {$starter} kit", true);
    }

    private function printNextSteps(Stack $stack): void
    {
        $this->newLine();
        $this->components->info('Inlay is installed.');

        $steps = [
            'Edit pages at '.url(config('inlay.admin.prefix', 'inlay')).'/pages - allowed only while APP_ENV=local until you change the gate in app/Providers/InlayServiceProvider.php.',
            'Add your own sections to resources/views/inlay/sections and blocks to resources/views/inlay/blocks.',
            'Run php artisan inlay:doctor at any time to check the setup.',
        ];

        if (! $stack->hasLivewire) {
            array_unshift($steps, 'Install Livewire for the admin: composer require livewire/livewire');
        }

        if ($stack->viteEntries !== []) {
            $steps[] = 'Run npm run build so the starter sections\' classes are compiled.';
        }

        $this->components->bulletList($steps);
    }

    private function installStarter(Filesystem $files, string $starter, string $css): void
    {
        $root = dirname(__DIR__, 2)."/stubs/starters/{$starter}";
        $source = $files->isDirectory("{$root}/{$css}") ? "{$root}/{$css}" : "{$root}/plain";

        $copies = [];

        foreach ($files->glob("{$source}/sections/*.blade.php") as $sectionType) {
            $copies[$sectionType] = resource_path('views/inlay/sections/'.basename($sectionType));
        }

        foreach ($files->glob("{$source}/blocks/*.blade.php") as $blockType) {
            $copies[$blockType] = resource_path('views/inlay/blocks/'.basename($blockType));
        }

        $copies["{$root}/LandingPage.php.stub"] = app_path('Inlay/LandingPage.php');

        if ($files->exists("{$source}/inlay.css")) {
            $copies["{$source}/inlay.css"] = resource_path('css/inlay.css');
        }

        $skipped = 0;

        foreach ($copies as $from => $to) {
            if ($files->exists($to) && ! $this->option('force')) {
                $skipped++;

                continue;
            }

            $files->ensureDirectoryExists(dirname($to));
            $files->copy($from, $to);
        }

        $copied = count($copies) - $skipped;
        $variant = basename($source);
        $this->report("The {$starter} starter ({$variant}, {$copied} files copied)", $copied > 0);

        if ($skipped > 0) {
            $this->components->warn("Kept {$skipped} existing files. Re-run with --force to overwrite them.");
        }

        if ($variant === 'plain') {
            $this->components->warn('Add @import "./inlay.css"; to your main stylesheet and rebrand it with the --inlay-* variables.');
        }
    }
}
