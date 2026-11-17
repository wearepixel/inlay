<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Import;

/**
 * Splits a fragment of markup into its top-level elements, keeping each
 * one's source exactly as written - Blade expressions, attributes and
 * whitespace included - rather than round-tripping it through a DOM parser
 * that would reformat it.
 */
final class TopLevelElements
{
    private const VOID_ELEMENTS = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /**
     * @return array<int, string> the source of each top-level element, in order
     */
    public static function split(string $markup): array
    {
        preg_match_all('/<(\/?)([a-zA-Z][a-zA-Z0-9-]*)\b[^>]*?(\/?)>/', $markup, $tags, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $elements = [];
        $depth = 0;
        $start = null;

        foreach ($tags as $tag) {
            $isClosing = $tag[1][0] === '/';
            $name = strtolower($tag[2][0]);
            $isVoid = $tag[3][0] === '/' || in_array($name, self::VOID_ELEMENTS, true);
            $offset = $tag[0][1];
            $end = $offset + strlen($tag[0][0]);

            if ($isClosing) {
                $depth--;

                if ($depth === 0 && $start !== null) {
                    $elements[] = substr($markup, $start, $end - $start);
                    $start = null;
                }

                continue;
            }

            if ($depth === 0) {
                $start = $offset;
            }

            if ($isVoid) {
                if ($depth === 0) {
                    $elements[] = substr($markup, $start, $end - $start);
                    $start = null;
                }

                continue;
            }

            $depth++;
        }

        return $elements;
    }

    /**
     * The part of a Blade page that holds its content: its "content" section
     * when it extends a layout, the slot of a page wrapped in a layout
     * component, or the body of a full HTML document.
     */
    public static function contentOf(string $blade): string
    {
        if (preg_match("/@section\\(\\s*'content'\\s*\\)(.*?)@(endsection|stop|show)/s", $blade, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/<body[^>]*>(.*)<\/body>/s', $blade, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/^\s*<x-([a-zA-Z0-9.:-]+)[^>]*>(.*)<\/x-\1>\s*$/s', $blade, $match) === 1) {
            return $match[2];
        }

        return $blade;
    }
}
