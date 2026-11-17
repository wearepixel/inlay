<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Import;

/**
 * Turns the hard-coded content in one element's markup into section
 * settings: headings and plain paragraphs become text fields, paragraphs
 * with inline formatting become rich text, images become image fields and
 * links become link fields. The markup is rewritten to output each setting.
 *
 * Anything that already contains Blade ({{ }}, @directives) is left as it
 * is, since it is the developer's logic rather than editable copy.
 */
final class ContentLifter
{
    /** @var array<int, array{name: string, type: string, label: string, default: mixed}> */
    private array $fields = [];

    /** @var array<string, int> */
    private array $nameCounts = [];

    private function __construct(private string $markup) {}

    /**
     * @return array{markup: string, fields: array<int, array{name: string, type: string, label: string, default: mixed}>}
     */
    public static function lift(string $markup): array
    {
        $lifter = new self($markup);

        $lifter->liftHeadings();
        $lifter->liftParagraphs();
        $lifter->liftLinks();
        $lifter->liftImages();

        return ['markup' => $lifter->markup, 'fields' => $lifter->fields];
    }

    private function liftHeadings(): void
    {
        $this->markup = (string) preg_replace_callback('/(<h([1-6])\b[^>]*>)([^<]+)(<\/h\2>)/i', function (array $match): string {
            $text = trim(html_entity_decode($match[3]));

            if (! self::isPlainCopy($text)) {
                return $match[0];
            }

            $name = $this->addField('heading', 'text', 'Heading', $text);

            return $match[1].'{{ $'.$name.' }}'.$match[4];
        }, $this->markup);
    }

    /**
     * A paragraph of plain text becomes a textarea; one with inline
     * formatting (links, bold, italics) becomes rich text.
     */
    private function liftParagraphs(): void
    {
        $this->markup = (string) preg_replace_callback('/(<p\b[^>]*>)(.*?)(<\/p>)/is', function (array $match): string {
            $inner = trim($match[2]);

            if ($inner === '' || ! self::isPlainCopy($inner) || preg_match('/<(div|p|ul|ol|section|img|h[1-6])\b/i', $inner) === 1) {
                return $match[0];
            }

            if (! str_contains($inner, '<')) {
                $name = $this->addField('text', 'textarea', 'Text', html_entity_decode($inner));

                return $match[1].'{{ $'.$name.' }}'.$match[3];
            }

            // Rich text holds whole paragraphs, so the <p> moves into the
            // content and its attributes stay on a wrapping <div>.
            $name = $this->addField('text', 'richtext', 'Text', '<p>'.$inner.'</p>');
            $wrapper = '<div'.substr($match[1], 2);

            return $wrapper.'{!! app(\\WeArePixel\\Inlay\\Support\\RichTextSanitizer::class)->sanitize($'.$name.') !!}</div>';
        }, $this->markup);
    }

    private function liftLinks(): void
    {
        $this->markup = (string) preg_replace_callback('/<a\b([^>]*?)\bhref="([^"]*)"([^>]*)>([^<]+)<\/a>/i', function (array $match): string {
            $label = trim(html_entity_decode($match[4]));
            $url = $match[2];

            if (! self::isPlainCopy($label) || ! self::isPlainCopy($url)) {
                return $match[0];
            }

            $name = $this->addField('link', 'link', 'Link', ['label' => $label, 'url' => $url, 'openInNewTab' => str_contains($match[0], '_blank')]);
            $attributes = self::withoutNewTabAttributes($match[1].'href="{{ $'.$name.'->url }}"'.$match[3]);
            $newTab = '@if ($'.$name.'->openInNewTab) target="_blank" rel="noopener" @endif';

            return '<a'.$attributes.' '.$newTab.'>{{ $'.$name.'->label }}</a>';
        }, $this->markup);
    }

    private function liftImages(): void
    {
        $this->markup = (string) preg_replace_callback('/<img\b[^>]*>/i', function (array $match): string {
            $tag = $match[0];

            if (preg_match('/\bsrc="([^"]*)"/i', $tag, $src) !== 1) {
                return $tag;
            }

            $path = self::publicImagePath($src[1]);

            if ($path === null) {
                return $tag;
            }

            $name = $this->addField('image', 'image', 'Image', $path);
            $tag = str_replace($src[0], 'src="{{ $'.$name.' }}"', $tag);

            if (preg_match('/\balt="([^"]*)"/i', $tag, $alt) === 1 && self::isPlainCopy($alt[1])) {
                $altName = $this->addField($name.'Alt', 'text', 'Image description', html_entity_decode($alt[1]));
                $tag = str_replace($alt[0], 'alt="{{ $'.$altName.' }}"', $tag);
            }

            return $tag;
        }, $this->markup);
    }

    /**
     * An image the application serves itself, as a root-relative path: a
     * plain "/images/x.jpg", or one written with asset('images/x.jpg').
     */
    private static function publicImagePath(string $src): ?string
    {
        if (preg_match("/^\\{\\{\\s*asset\\(\\s*'([^']+)'\\s*\\)\\s*\\}\\}$/", $src, $asset) === 1) {
            return '/'.ltrim($asset[1], '/');
        }

        if (str_starts_with($src, '/') && ! str_starts_with($src, '//') && self::isPlainCopy($src)) {
            return $src;
        }

        return null;
    }

    /**
     * The link's "open in new tab" setting now decides target and rel.
     */
    private static function withoutNewTabAttributes(string $attributes): string
    {
        return (string) preg_replace('/\s(target|rel)="[^"]*"/i', '', $attributes);
    }

    private static function isPlainCopy(string $text): bool
    {
        return $text !== ''
            && ! str_contains($text, '{{')
            && ! str_contains($text, '{!!')
            && preg_match('/(^|\s)@[a-z]/i', $text) !== 1;
    }

    private function addField(string $base, string $type, string $label, mixed $default): string
    {
        $count = ($this->nameCounts[$base] ?? 0) + 1;
        $this->nameCounts[$base] = $count;

        $name = $count === 1 ? $base : $base.$count;
        $this->fields[] = [
            'name' => $name,
            'type' => $type,
            'label' => $count === 1 ? $label : "{$label} {$count}",
            'default' => $default,
        ];

        return $name;
    }
}
