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
use WeArePixel\Inlay\Console\Install\StepResult;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinition;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

final class InstallCommand extends Command
{
    protected $signature = 'inlay:install
        {--starter= : Copy a starter kit (landing, showcase, or none)}
        {--css= : Starter styling: tailwind, bootstrap or plain (detected from package.json)}
        {--layout= : Render pages inside an existing layout, e.g. component:layouts.site or extends:layouts.app}
        {--demo : Create and publish a homepage from the starter kit}
        {--force : Overwrite starter files that already exist}';

    protected $description = 'Install Inlay: detect the stack, wire up routes, views and the admin, and optionally add a starter kit';

    private const STARTERS = ['landing', 'showcase'];

    private const CSS_VARIANTS = ['tailwind', 'bootstrap', 'plain'];

    /** @var array<string, array{class: string, handle: string}> */
    private const STARTER_PAGE_TYPES = [
        'landing' => ['class' => 'App\\Inlay\\LandingPage', 'handle' => 'landing-page'],
        'showcase' => ['class' => 'App\\Inlay\\ShowcasePage', 'handle' => 'showcase-page'],
    ];

    /** @var array<int, string> steps the installer could not do safely, shown at the end */
    private array $todos = [];

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

        $starterSource = $starter !== null ? $this->starterSource($files, $starter, $css) : null;

        if ($starter !== null && $starterSource === null) {
            $this->components->error("The {$starter} kit has no {$css} version. Use --css=tailwind or the landing kit.");

            return self::FAILURE;
        }

        $this->publishConfig();
        $this->runMigrations();

        if ($starterSource !== null) {
            $this->installStarter($files, (string) $starter, $starterSource);
        }

        $this->wireUp($wiring, $stack, $starter, $css);

        if ($starter !== null && $this->shouldCreateDemo($stack)) {
            $this->createDemoHomepage($starter);
        }

        $this->printNextSteps($stack);

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $published = $this->callSilently('vendor:publish', ['--tag' => 'inlay-config']) === self::SUCCESS;

        $this->report('config/inlay.php', $published ? StepResult::done() : StepResult::needsYou('Run php artisan vendor:publish --tag=inlay-config.'));
    }

    /**
     * Runs every pending migration, the host's included, so in production
     * it asks first rather than assuming.
     */
    private function runMigrations(): void
    {
        if ($this->laravel->isProduction() && (! $this->input->isInteractive() || ! confirm('Run pending migrations on this production database?', default: false))) {
            $this->report('Database tables', StepResult::needsYou('Run php artisan migrate.'));

            return;
        }

        $migrated = $this->callSilently('migrate', ['--force' => true]) === self::SUCCESS;

        $this->report('Database tables', $migrated ? StepResult::done() : StepResult::needsYou('Run php artisan migrate and check its output.'));
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
            options: [
                'landing' => 'Landing kit - hero, features, pricing, FAQ and more (Tailwind, Bootstrap or plain CSS)',
                'showcase' => 'Showcase kit - bento grid, layered hero, logo marquee, testimonial wall (Tailwind)',
                'none' => 'No, I will build my own',
            ],
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

        if ($css === 'tailwind') {
            $this->report('Admin theme in resources/css/app.css', $wiring->writeAdminTheme());
        }
    }

    private function report(string $what, StepResult $result): void
    {
        $badge = match ($result->status) {
            'done' => '<fg=green;options=bold>DONE</>',
            'already set up' => '<fg=gray>already set up</>',
            'not needed' => '<fg=gray>not needed</>',
            default => '<fg=yellow;options=bold>NEEDS YOU</>',
        };

        $this->components->twoColumnDetail($what, $badge);

        if ($result->todo !== null) {
            $this->todos[] = $result->todo;
        }
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

        $this->loadCopiedPageType($class);

        if (! is_a($class, PageDefinition::class, true)) {
            return;
        }

        Inlay::pageDefinition($class);
        $definition = app(PageDefinitionRegistry::class)->definition($handle) ?? app(PageDefinitionRegistry::class)->default();

        $page = app(CreatePage::class)->execute(definition: $definition, title: 'Home', isHomepage: true);
        app(PublishPage::class)->execute($page, label: 'Created by inlay:install');

        $this->report("Homepage built from the {$starter} kit", StepResult::done());
    }

    /**
     * The page type was just copied into app/Inlay, after Composer's
     * class map was built, so it may need loading by hand.
     */
    private function loadCopiedPageType(string $class): void
    {
        if (! class_exists($class)) {
            require_once app_path('Inlay/'.class_basename($class).'.php');
        }
    }

    private function printNextSteps(Stack $stack): void
    {
        $this->newLine();
        $this->components->info('Inlay is installed.');

        $steps = [
            ...$this->todos,
            'Edit pages at '.url(config('inlay.admin.prefix', 'inlay')).'/pages - allowed only while APP_ENV=local until you change the gate in app/Providers/InlayServiceProvider.php.',
            'Add your own sections to resources/views/inlay/sections and blocks to resources/views/inlay/blocks.',
            'Run php artisan inlay:doctor at any time to check the setup.',
        ];

        if (! $stack->hasLivewire) {
            array_unshift($steps, 'Install Livewire for the admin: composer require livewire/livewire');
        }

        if ($stack->registersRoutesAfterWeb) {
            $steps[] = 'bootstrap/app.php registers routes after routes/web.php (then: or pages:). Inlay::pageRoutes() answers every URL, so call it after those instead.';
        }

        if ($stack->viteEntries !== []) {
            $steps[] = 'Run npm run build so the starter sections\' classes are compiled.';
        }

        $this->components->bulletList($steps);
    }

    /**
     * The kit's folder for the chosen CSS, falling back to its plain CSS
     * variant, or null when the kit has neither.
     */
    private function starterSource(Filesystem $files, string $starter, string $css): ?string
    {
        $root = dirname(__DIR__, 2)."/stubs/starters/{$starter}";

        foreach ([$css, 'plain'] as $variant) {
            if ($files->isDirectory("{$root}/{$variant}")) {
                return "{$root}/{$variant}";
            }
        }

        return null;
    }

    private function installStarter(Filesystem $files, string $starter, string $source): void
    {
        $root = dirname($source);

        $copies = [];

        foreach ($files->glob("{$source}/sections/*.blade.php") as $sectionType) {
            $copies[$sectionType] = resource_path('views/inlay/sections/'.basename($sectionType));
        }

        foreach ($files->glob("{$source}/blocks/*.blade.php") as $blockType) {
            $copies[$blockType] = resource_path('views/inlay/blocks/'.basename($blockType));
        }

        $pageType = class_basename(self::STARTER_PAGE_TYPES[$starter]['class']);
        $copies["{$root}/{$pageType}.php.stub"] = app_path("Inlay/{$pageType}.php");

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
        $this->report("The {$starter} starter ({$variant}, {$copied} files copied)", $copied > 0 ? StepResult::done() : StepResult::alreadySetUp());

        if ($skipped > 0) {
            $this->components->warn("Kept {$skipped} existing files. Re-run with --force to overwrite them.");
        }

        if ($variant === 'plain') {
            $this->components->warn('Add @import "./inlay.css"; to your main stylesheet and rebrand it with the --inlay-* variables.');
        }
    }
}
