<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Composition;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;
use Throwable;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;

/**
 * Describes what a composition will actually render, section by section,
 * so an agent can check its work without seeing the page: the text,
 * headings, links and images each block produced, the content still coming
 * from block defaults, and anything that failed or looks unfinished.
 */
final class CompositionReport
{
    private const CONTENT_FIELD_TYPES = [FieldType::Text, FieldType::Textarea, FieldType::Richtext, FieldType::Image, FieldType::Link];

    private const TEXT_EXCERPT_LENGTH = 400;

    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly SectionRenderer $renderer,
    ) {}

    /**
     * @param  array<int, array{handle: string, settings?: array<string, mixed>, id?: int|null}>  $sections  stored (sparse) settings
     * @return array{sections: array<int, array<string, mixed>>, warnings: array<int, string>}
     */
    public function build(array $sections): array
    {
        $report = [];
        $warnings = [];
        $h1Count = 0;

        foreach (array_values($sections) as $index => $section) {
            $entry = $this->describeSection($index, $section);
            $h1Count += count(array_filter($entry['outline']['headings'] ?? [], fn (array $heading) => $heading['level'] === 1));

            foreach ($entry['problems'] as $problem) {
                $warnings[] = "Section {$index} ({$entry['block']}): {$problem}";
            }

            $report[] = $entry;
        }

        if ($h1Count > 1) {
            $warnings[] = "The page renders {$h1Count} <h1> headings. Most pages should have exactly one.";
        }

        return ['sections' => $report, 'warnings' => $warnings];
    }

    /**
     * @param  array{handle: string, settings?: array<string, mixed>, id?: int|null}  $section
     * @return array<string, mixed>
     */
    private function describeSection(int $index, array $section): array
    {
        $handle = $section['handle'];
        $stored = $section['settings'] ?? [];
        $entry = ['index' => $index, 'id' => $section['id'] ?? null, 'block' => $handle, 'problems' => []];

        if (! $this->blocks->hasHandle($handle)) {
            $entry['problems'][] = 'This block is not registered and will not render.';

            return $entry;
        }

        $definition = $this->blocks->definition($handle);
        $entry['name'] = $definition->name;
        $entry['defaults_in_use'] = $this->defaultsInUse($definition->settings->fields(), $stored);

        try {
            $html = $this->renderer->renderSection(['handle' => $handle, 'settings' => $stored]);
        } catch (Throwable $exception) {
            $entry['problems'][] = 'Failed to render: '.Str::limit($exception->getMessage(), 300);

            return $entry;
        }

        $entry['outline'] = $this->outline($html);
        array_push($entry['problems'], ...$this->outlineProblems($entry['outline']));

        return $entry;
    }

    /**
     * @param  array<int, \WeArePixel\Inlay\Schema\Setting>  $fields
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function defaultsInUse(array $fields, array $stored): array
    {
        $inUse = [];

        foreach ($fields as $field) {
            if (array_key_exists($field->name, $stored) || ! in_array($field->type, self::CONTENT_FIELD_TYPES, true)) {
                continue;
            }

            $default = $field->default;

            if ($default === null || $default === '' || (is_object($default) && trim((string) json_encode($default)) === '{}')) {
                continue;
            }

            $inUse[$field->name] = $default;
        }

        return $inUse;
    }

    /**
     * @return array{text: string, headings: array<int, array{level: int, text: string}>, links: array<int, array{text: string, href: string}>, images: array<int, array{src: string, alt: string|null}>}
     */
    private function outline(string $html): array
    {
        $outline = ['text' => '', 'headings' => [], 'links' => [], 'images' => []];

        if (trim($html) === '') {
            return $outline;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') ?: [] as $heading) {
            $outline['headings'][] = ['level' => (int) substr($heading->nodeName, 1), 'text' => $this->squish($heading->textContent)];
        }

        foreach ($xpath->query('//a') ?: [] as $link) {
            /** @var DOMElement $link */
            $outline['links'][] = ['text' => $this->squish($link->textContent), 'href' => $link->getAttribute('href')];
        }

        foreach ($xpath->query('//img') ?: [] as $image) {
            /** @var DOMElement $image */
            $outline['images'][] = ['src' => $image->getAttribute('src'), 'alt' => $image->hasAttribute('alt') ? $image->getAttribute('alt') : null];
        }

        $outline['text'] = Str::limit($this->squish($document->textContent), self::TEXT_EXCERPT_LENGTH);

        return $outline;
    }

    /**
     * @param  array{text: string, headings: array<int, mixed>, links: array<int, array{text: string, href: string}>, images: array<int, array{src: string, alt: string|null}>}  $outline
     * @return array<int, string>
     */
    private function outlineProblems(array $outline): array
    {
        $problems = [];

        if ($outline['text'] === '' && $outline['images'] === []) {
            $problems[] = 'Renders no visible content.';
        }

        foreach ($outline['links'] as $link) {
            if (in_array(trim($link['href']), ['', '#'], true)) {
                $problems[] = "Link \"{$link['text']}\" has no destination.";
            }
        }

        foreach ($outline['images'] as $image) {
            if ($image['src'] === '') {
                $problems[] = 'An image has no source.';
            } elseif ($image['alt'] === null) {
                $problems[] = "Image {$image['src']} has no alt text.";
            }
        }

        return $problems;
    }

    private function squish(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
