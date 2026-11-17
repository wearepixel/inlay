<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Styling;

use Illuminate\Support\Facades\Cache;

/**
 * The classes and theme variables the site's compiled CSS actually defines.
 * Agent-authored sections may only use these, so every class an agent writes
 * is guaranteed to be styled, and nothing outside the site's design system
 * (arbitrary values, another framework, inline styles) can get in.
 */
final class CompiledStylesheets
{
    private const CACHE_KEY = 'inlay:compiled-stylesheets';

    private const TOKEN_PREFIXES = ['--color-', '--font-', '--text-', '--spacing', '--radius', '--shadow', '--breakpoint-', '--container-', '--bs-'];

    /**
     * @var array{classes: array<string, true>, tokens: array<string, string>}|null
     */
    private ?array $parsed = null;

    /**
     * @return array<int, string>
     */
    public function paths(): array
    {
        $paths = array_values(array_filter(
            (array) config('inlay.styling.stylesheets', []),
            fn (mixed $path) => is_string($path) && is_file($path),
        ));

        $manifest = config('inlay.styling.vite_manifest') ?? public_path('build/manifest.json');

        if (is_string($manifest) && is_file($manifest)) {
            array_push($paths, ...$this->cssFromManifest($manifest));
        }

        return array_values(array_unique($paths));
    }

    public function available(): bool
    {
        return $this->classes() !== [];
    }

    /**
     * Keyed by class name. A purely numeric class name becomes an int key, so
     * cast keys back to string when iterating.
     *
     * @return array<array-key, true>
     */
    public function classes(): array
    {
        return $this->parsed()['classes'];
    }

    /**
     * Denied utilities (by default `fixed`, which lets a section overlay the
     * whole page) are refused with any variant, even when the CSS defines them.
     */
    public function allows(string $class): bool
    {
        $parts = explode(':', $class);
        $utility = ltrim((string) array_pop($parts), '!');

        if (in_array($utility, (array) config('inlay.styling.denied_classes', ['fixed']), true)) {
            return false;
        }

        return isset($this->classes()[$class]);
    }

    /**
     * @return array<string, string>
     */
    public function tokens(): array
    {
        return $this->parsed()['tokens'];
    }

    /**
     * Allowed classes closest to an unknown one, for error messages.
     *
     * @return array<int, string>
     */
    public function suggest(string $class, int $limit = 5): array
    {
        $stem = explode('-', ltrim(str_contains($class, ':') ? substr($class, strrpos($class, ':') + 1) : $class, '-'))[0];
        $scores = [];

        foreach (array_keys($this->classes()) as $candidate) {
            $candidate = (string) $candidate;

            if ($stem !== '' && ! str_contains($candidate, $stem)) {
                continue;
            }

            $scores[$candidate] = levenshtein($class, $candidate);
        }

        asort($scores);

        return array_slice(array_keys($scores), 0, $limit);
    }

    /**
     * @return array{classes: array<string, true>, tokens: array<string, string>}
     */
    private function parsed(): array
    {
        if ($this->parsed !== null) {
            return $this->parsed;
        }

        $paths = $this->paths();
        $signature = md5(implode('|', array_map(fn (string $path) => $path.':'.filemtime($path), $paths)).json_encode(config('inlay.styling.extra_classes', [])));
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && ($cached['signature'] ?? null) === $signature) {
            return $this->parsed = $cached['parsed'];
        }

        $this->parsed = (function () use ($paths) {
            $classes = [];
            $tokens = [];

            foreach ($paths as $path) {
                $css = (string) file_get_contents($path);
                $classes += $this->parseClasses($css);
                $tokens += $this->parseTokens($css);
            }

            foreach ((array) config('inlay.styling.extra_classes', []) as $extra) {
                if (is_string($extra) && $extra !== '') {
                    $classes[$extra] = true;
                }
            }

            ksort($classes);
            ksort($tokens);

            return ['classes' => $classes, 'tokens' => $tokens];
        })();

        Cache::forever(self::CACHE_KEY, ['signature' => $signature, 'parsed' => $this->parsed]);

        return $this->parsed;
    }

    /**
     * Class names from selector preludes only - declaration bodies hold values
     * like `.5rem` or `image.png` that would otherwise read as classes.
     *
     * @return array<string, true>
     */
    public function parseClasses(string $css): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        $classes = [];

        preg_match_all('/([^{}]+)\{/', $css, $preludes);

        foreach ($preludes[1] as $prelude) {
            $prelude = (string) preg_replace(['/(?<!\\\\)\[[^\]]*(?<!\\\\)\]/', '/"[^"]*"|\'[^\']*\'/'], '', $prelude);

            if (str_starts_with(ltrim($prelude), '@') && ! str_contains($prelude, '.')) {
                continue;
            }

            preg_match_all('/\.(-?(?:[A-Za-z_]|\\\\[0-9a-fA-F]{1,6}\s?|\\\\.)(?:\\\\[0-9a-fA-F]{1,6}\s?|\\\\.|[\w-])*)/u', $prelude, $matches);

            foreach ($matches[1] as $escaped) {
                $classes[$this->unescape($escaped)] = true;
            }
        }

        return $classes;
    }

    /**
     * @return array<string, string>
     */
    public function parseTokens(string $css): array
    {
        $tokens = [];

        preg_match_all('/(--[A-Za-z0-9_-]+)\s*:\s*([^;{}]+)[;}]/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $name, $value]) {
            foreach (self::TOKEN_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $tokens[$name] ??= trim($value);

                    break;
                }
            }
        }

        return $tokens;
    }

    private function unescape(string $escaped): string
    {
        $hex = (string) preg_replace_callback(
            '/\\\\([0-9a-fA-F]{1,6})\s?/',
            fn (array $match) => mb_chr((int) hexdec($match[1])),
            $escaped,
        );

        return (string) preg_replace('/\\\\(.)/u', '$1', $hex);
    }

    /**
     * @return array<int, string>
     */
    private function cssFromManifest(string $manifest): array
    {
        $entries = json_decode((string) file_get_contents($manifest), true);

        if (! is_array($entries)) {
            return [];
        }

        $base = dirname($manifest);
        $files = [];
        $only = config('inlay.styling.vite_entries');

        foreach ($entries as $key => $entry) {
            if (! is_array($entry) || (is_array($only) && ! in_array($key, $only, true) && ! in_array($entry['src'] ?? null, $only, true))) {
                continue;
            }

            foreach ([$entry['file'] ?? null, ...($entry['css'] ?? [])] as $file) {
                if (is_string($file) && str_ends_with($file, '.css') && is_file($base.'/'.$file)) {
                    $files[] = $base.'/'.$file;
                }
            }
        }

        return $files;
    }
}
