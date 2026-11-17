<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Import;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WeArePixel\Inlay\Actions\CreatePage;
use WeArePixel\Inlay\Actions\ReplacePageComposition;
use WeArePixel\Inlay\Facades\Inlay;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Support\SectionRegistry;

/**
 * Turns an existing Blade page into an editable draft page: one section
 * file per top-level element, with its copy, images and links lifted into
 * settings, then a page composed of those sections in order.
 */
final class BladePageImporter
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly CreatePage $createPage,
        private readonly ReplacePageComposition $compose,
        private readonly PageDefinitionRegistry $definitions,
        private readonly SectionRegistry $sections,
    ) {}

    /**
     * Works out the sections without writing anything. Handles are chosen
     * so none clashes with a section that is already registered or already
     * has a file in $sectionsDirectory.
     */
    public function plan(string $bladePath, string $pageName, string $sectionsDirectory): ImportPlan
    {
        $content = TopLevelElements::contentOf($this->files->get($bladePath));
        $sections = [];
        $takenHandles = $this->existingHandles($sectionsDirectory);

        foreach (TopLevelElements::split($content) as $index => $element) {
            $handle = $this->uniqueHandle($pageName, $element, $index, $takenHandles);
            $takenHandles[] = $handle;
            $lifted = ContentLifter::lift(self::dedent($element));

            $sections[] = new ImportedSection(
                handle: $handle,
                name: Str::headline($pageName).': '.Str::headline(Str::after($handle, Str::slug($pageName).'-')),
                markup: $lifted['markup'],
                fields: $lifted['fields'],
            );
        }

        return new ImportPlan($sections, TopLevelElements::textBetween($content));
    }

    /**
     * Writes each section file into $sectionsDirectory and creates a draft
     * page using them. plan() chose handles no existing file uses.
     *
     * @param  array<int, ImportedSection>  $sections
     */
    public function import(array $sections, string $sectionsDirectory, string $sourcePath, string $title, ?string $path): Page
    {
        $this->files->ensureDirectoryExists($sectionsDirectory);

        foreach ($sections as $section) {
            $file = "{$sectionsDirectory}/{$section->handle}.blade.php";
            $this->files->put($file, $section->toBlade($sourcePath));
            Inlay::singleFileSection($file);
        }

        $composition = [];

        foreach ($sections as $section) {
            $composition[] = ['section' => $section->handle];
        }

        return DB::transaction(function () use ($title, $path, $composition): Page {
            $page = $this->createPage->execute(definition: $this->definitions->default(), title: $title, path: $path);
            $this->compose->execute($page, $composition);

            return $page->refresh();
        });
    }

    /**
     * @return array<int, string>
     */
    private function existingHandles(string $sectionsDirectory): array
    {
        $handles = $this->sections->slugs();

        foreach ($this->files->glob("{$sectionsDirectory}/*.blade.php") as $file) {
            $handles[] = basename($file, '.blade.php');
        }

        return $handles;
    }

    /**
     * An element cut out of a page starts at its "<" but its other lines keep
     * the page's indentation; this lines them up again.
     */
    private static function dedent(string $element): string
    {
        $lines = explode("\n", $element);
        $indents = [];

        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) !== '') {
                $indents[] = strlen($line) - strlen(ltrim($line, ' '));
            }
        }

        if ($indents === []) {
            return $element;
        }

        $remove = min($indents);

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $lines[$index] = substr($line, min($remove, strlen($line) - strlen(ltrim($line, ' '))));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Named after the element's id, then its first class, then its first
     * heading, falling back to its position on the page.
     *
     * @param  array<int, string>  $takenHandles
     */
    private function uniqueHandle(string $pageName, string $element, int $index, array $takenHandles): string
    {
        $name = match (true) {
            preg_match('/^<[a-zA-Z0-9-]+[^>]*\bid="([^"{]+)"/', $element, $match) === 1 => $match[1],
            preg_match('/^<[a-zA-Z0-9-]+[^>]*\bclass="([^"{\s]+)/', $element, $match) === 1 => $match[1],
            preg_match('/<h[1-6][^>]*>([^<{]+)<\/h[1-6]>/', $element, $match) === 1 => Str::words($match[1], 4, ''),
            default => 'section-'.($index + 1),
        };

        $handle = Str::slug($pageName.'-'.$name);
        $candidate = $handle;
        $suffix = 2;

        while (in_array($candidate, $takenHandles, true)) {
            $candidate = "{$handle}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
