<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Preview;

/**
 * Marks a rendered section or block so the editor preview can outline it
 * and select it on click. The data attributes go on the markup's root
 * element when it has exactly one, leaving the page's structure - and any
 * CSS that depends on it - untouched. Markup with several root elements is
 * wrapped in a display:contents element instead.
 */
final class PreviewTag
{
    private const VOID_ELEMENTS = ['area', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr'];

    /**
     * @param  array<string, string>  $attributes
     */
    public static function apply(string $html, array $attributes): string
    {
        $rendered = '';

        foreach ($attributes as $name => $value) {
            $rendered .= ' '.$name.'="'.e($value).'"';
        }

        $html = trim($html);
        $rootTag = self::singleRootTag($html);

        if ($rootTag === null) {
            return '<div'.$rendered.' style="display: contents">'.$html.'</div>';
        }

        return '<'.$rootTag.$rendered.substr($html, strlen($rootTag) + 1);
    }

    /**
     * The tag name of the markup's only root element, or null when it has
     * none (text first) or more than one.
     */
    private static function singleRootTag(string $html): ?string
    {
        if (preg_match('/^<([a-zA-Z][a-zA-Z0-9-]*)/', $html, $match) !== 1) {
            return null;
        }

        $tag = $match[1];

        if (in_array(strtolower($tag), self::VOID_ELEMENTS, true)) {
            return preg_match('/^<[^>]*>$/', $html) === 1 ? $tag : null;
        }

        return self::rootClosesAtEnd($html, $tag) ? $tag : null;
    }

    /**
     * Walks every opening and closing tag with the root's name, tracking how
     * deeply they nest. The root is the only one when the tag that brings the
     * depth back to zero is the last thing in the markup - so
     * "<div>a</div><div>b</div>" (two roots) is told apart from
     * "<div><div>a</div></div>" (one).
     */
    private static function rootClosesAtEnd(string $html, string $tag): bool
    {
        $pattern = '/<(\/?)'.preg_quote($tag, '/').'\b[^>]*>/i';
        preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $depth = 0;

        foreach ($matches as $match) {
            $isClosing = $match[1][0] === '/';
            $depth += $isClosing ? -1 : 1;

            if ($depth === 0) {
                $endOfTag = $match[0][1] + strlen($match[0][0]);

                return $endOfTag === strlen($html);
            }
        }

        return false;
    }
}
