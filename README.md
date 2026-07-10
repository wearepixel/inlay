# Blade Page Builder

A drag-and-drop, schema-driven page builder engine for Laravel Blade components.

## Install

    composer require wearepixel/blade-page-builder
    php artisan vendor:publish --tag=blade-page-builder-config
    php artisan migrate

## Usage

1. Write a section as a Blade class component implementing `Sectionable`:

```php
class HeroSplit extends \Illuminate\View\Component implements \WearePixel\BladePageBuilder\Contracts\Sectionable
{
    public function __construct(
        public string $heading = '',
        public string $layout = 'image-right',
    ) {}

    public static function schema(): \WearePixel\BladePageBuilder\Schema\Schema
    {
        return \WearePixel\BladePageBuilder\Schema\Schema::make()
            ->text('heading', label: 'Heading')
            ->select('layout', label: 'Layout', options: [
                'image-left' => 'Image on left',
                'image-right' => 'Image on right',
            ])->mapsToClass(fn (string $value) => $value === 'image-left' ? 'flex-row-reverse' : 'flex-row');
    }

    public function render(): \Illuminate\View\View
    {
        return view('sections.hero-split');
    }
}
```

2. Register it in `config/blade-page-builder.php`:

```php
'sections' => [
    'hero-split' => \App\Sections\HeroSplit::class,
],
```

3. Mount the editor at your own admin route:

```blade
<livewire:page-builder::page-editor :page="$page" />
```

4. Render the published page on the public site:

```blade
<x-page-builder::render :page="$page" />
```

## Security note: `richtext` and `link` fields

Both field types carry content supplied by whoever has access to the page editor, and both end up in your section templates' markup.

- **`link`**: the package restricts the `url` sub-value to `http://`, `https://`, `mailto:`, and relative URLs, rejecting other schemes (e.g. `javascript:`) before the value is ever persisted. You can render it into an `href` attribute without adding your own scheme check.
- **`richtext`**: the package does **not** sanitize this field's HTML. If your section template renders it with `{!! !!}` (required to preserve formatting), any markup an editor enters is output as-is. This is safe only if you trust everyone with editor access; if you don't, sanitize the value yourself (e.g. with an HTML purifier) before rendering it raw.
