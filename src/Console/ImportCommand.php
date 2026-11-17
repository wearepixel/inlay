<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Import\BladePageImporter;
use WeArePixel\Inlay\Rules\PagePath;

final class ImportCommand extends Command
{
    protected $signature = 'inlay:import
        {view : Path to the Blade file, e.g. resources/views/pages/about.blade.php}
        {--title= : Page title (defaults from the file name)}
        {--path= : Public path for the page, e.g. about (none by default)}
        {--dry-run : Show the sections that would be created without writing anything}';

    protected $description = 'Turn an existing Blade page into editable sections and a draft page';

    public function handle(BladePageImporter $importer): int
    {
        $source = $this->argument('view');
        $absolute = str_starts_with($source, '/') ? $source : base_path($source);

        if (! is_file($absolute)) {
            $this->components->error("No file at [{$source}].");

            return self::FAILURE;
        }

        $pageName = Str::of(basename($absolute))->before('.')->toString();
        $title = $this->option('title') ?? Str::headline($pageName);
        $path = $this->option('path');

        if ($path !== null && validator(['path' => $path], ['path' => [new PagePath]])->fails()) {
            $this->components->error("[{$path}] is not a usable page path or is already taken.");

            return self::FAILURE;
        }

        $sections = $importer->plan($absolute, $pageName);

        if ($sections === []) {
            $this->components->error('No top-level elements found to turn into sections.');

            return self::FAILURE;
        }

        foreach ($sections as $section) {
            $fieldNames = array_map(fn (array $field) => $field['name'], $section->fields);
            $summary = $fieldNames === [] ? 'no editable content found' : implode(', ', $fieldNames);
            $this->components->twoColumnDetail($section->handle, $summary);
        }

        if ($this->option('dry-run')) {
            $this->components->info('Dry run - nothing was written.');

            return self::SUCCESS;
        }

        $page = $importer->import($sections, resource_path('views/inlay/sections'), $source, $title, $path);

        $this->newLine();
        $this->components->info("Created a draft page \"{$page->title}\" with ".count($sections).' sections.');
        $this->components->bulletList([
            'Review the generated files in resources/views/inlay/sections - rename settings, fix labels, and put back any text that should not be editable.',
            'Open the page in the admin to edit it, then publish: '.Inlay::adminUrl('pages.edit', $page),
            'Repeated markup such as cards is imported as separate fields; turn it into a repeater or a blocks area by hand.',
        ]);

        return self::SUCCESS;
    }
}
