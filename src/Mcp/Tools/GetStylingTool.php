<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use WeArePixel\Inlay\Mcp\InlayTool;
use WeArePixel\Inlay\Styling\CompiledStylesheets;

#[Name('inlay-get-styling')]
#[Title('Get the site\'s styling')]
#[IsReadOnly]
#[Description(<<<'TEXT'
Returns the CSS framework this site uses, its design tokens (colours, fonts, spacing, radii with their
values) and every class its compiled CSS defines, grouped by utility.

Call this before authoring a section with inlay-save-section. A section may use ONLY these classes - anything
else is rejected. Map a design's colours, font sizes and spacing to the nearest token-based class
rather than trying to match exact pixel values. Pass "search" to filter, e.g. "bg-" or "grid".
TEXT)]
final class GetStylingTool extends InlayTool
{
    private const GUIDANCE = [
        'tailwind' => 'This site uses Tailwind CSS. Compose with its utility classes, responsive prefixes (sm:, md:, lg:) and state variants as listed.',
        'bootstrap' => 'This site uses Bootstrap. Compose with its grid (container, row, col-*), components and utilities as listed.',
        'plain' => 'This site uses its own CSS. Use the component classes as listed (for example inlay-hero, inlay-hero__title).',
    ];

    private const FULL_LIST_LIMIT = 1500;

    public function handle(Request $request, CompiledStylesheets $styles): Response|ResponseFactory
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        if (! $styles->available()) {
            return Response::error("No compiled stylesheet was found, so the site's classes are unknown. A developer must build the assets or set inlay.styling.stylesheets.");
        }

        $search = trim((string) $request->get('search', ''));
        $framework = (string) config('inlay.styling.framework', 'tailwind');
        $groups = [];
        $variants = [];

        foreach (array_keys($styles->classes()) as $class) {
            $class = (string) $class;

            if ($search !== '' && ! str_contains($class, $search)) {
                continue;
            }

            $parts = preg_split('/:(?![^\[]*\])/', $class) ?: [$class];
            $utility = array_pop($parts);

            foreach ($parts as $variant) {
                $variants[$variant] = true;
            }

            $group = explode('-', ltrim($utility, '-'))[0] ?: $utility;
            $groups[$group][] = $class;
        }

        ksort($groups);
        $count = array_sum(array_map('count', $groups));
        $summarised = $search === '' && $count > self::FULL_LIST_LIMIT;

        return Response::structured([
            'framework' => $framework,
            'guidance' => self::GUIDANCE[$framework] ?? 'Use only the classes listed.',
            'tokens' => $search === '' ? $styles->tokens() : [],
            'variants' => array_keys($variants),
            'class_count' => $count,
            'classes' => $summarised
                ? array_map(fn (array $classes) => count($classes).' classes, e.g. '.implode(' ', array_slice($classes, 0, 6)), $groups)
                : array_map(fn (array $classes) => implode(' ', $classes), $groups),
            'note' => $summarised ? 'Too many classes to list at once. Call again with "search" (e.g. "bg-", "text-", "grid") for the full list in a group.' : null,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Only return classes containing this text.'),
        ];
    }
}
