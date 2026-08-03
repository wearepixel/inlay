# Inlay

A Laravel-native visual composition layer for existing applications. A developer registers ordinary Blade components as blocks; Inlay gives the people who work with content a packaged admin to compose pages from them, edit their settings, preview against the real frontend, and publish - without the developer building any admin UI of their own.

## Quick start

```bash
composer require wearepixel/inlay
php artisan inlay:install
```

```php
// routes/web.php
use WeArePixel\Inlay\Facades\Inlay;

Inlay::adminRoutes(); // -> /inlay/pages
```

```php
// app/Inlay/Blocks/Hero.php
namespace App\Inlay\Blocks;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Contracts\InlayBlock;

final class Hero extends Component implements InlayBlock
{
    public function __construct(
        public string $heading = 'Welcome',
    ) {}

    public function render(): View
    {
        return view('blocks.hero'); // resources/views/blocks/hero.blade.php
    }
}
```

```php
// config/inlay.php
'blocks' => [
    'hero' => \App\Inlay\Blocks\Hero::class,
],
```

Visit `/inlay/pages`, create a page, add your Hero block from the picker, and edit its heading - the preview pane reloads against your own `blocks.hero` view as you type. Everything below covers registering settings, page routing, and Page Definitions in full; see [docs/installation.md](docs/installation.md) for the complete setup including middleware and a `previewView()`.

## Core concepts

Four things compose together, each covered in its own section below:

| Concept             | What it is                                                                                                                                |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| **Block**           | A reusable Blade component you register once (`Hero`, `ImageWithText`) - see [Registering blocks](#registering-blocks).                   |
| **Page Section**    | One placed instance of a Block on a specific Page, with its own settings overrides - created when an editor adds a Block from the picker. |
| **Page**            | An ordered list of Page Sections, plus its own title, public path, and metadata - see [Working with pages](#working-with-pages).          |
| **Page Definition** | An optional developer-authored policy restricting which Blocks a kind of Page may contain - see [Page Definitions](#page-definitions).    |

## What Inlay is

- A package you install into an **existing** Laravel application, not a standalone CMS or a new application you build on top of.
- A way to expose a **safe, schema-driven subset** of your own Blade components as blocks for visual composition, with the schema derived from your block's own constructor - not a separate config file that can drift out of sync with your code.
- Frontend-agnostic: Inlay renders your blocks through Laravel's real Blade/Livewire pipeline and never ships its own frontend framework, CSS reset, or JavaScript bundler dependency into your application's output. Your blocks look and behave exactly as you built them, in whatever design system your application already uses.
- Pre-release. The public API described below is the current, intended shape, but this is not yet a stable 1.0.

## What Inlay is not

- **Not a page builder that generates markup.** You write real Blade components as blocks; Inlay composes and persists references to them plus their settings. It never invents HTML.
- **Not a theming system.** There is no Inlay design language leaking into your public pages - only the admin has its own (self-hosted, Tailwind-free) design.
- **Not a drag-and-drop layout engine.** A page is a flat, ordered list of blocks rendered inside whatever layout your own route or controller already provides. There are no nested blocks, slots, or regions in this release.
- **Not a rich-text platform, a media library, or a navigation builder.** These are explicitly out of scope for the current release - see [Current limitations](#current-limitations).
- **Not Livewire Volt**, even where the ergonomics rhyme (see [single-file blocks](docs/blocks.md#single-file-blocks) for the authoring style Inlay uses instead).

## Installation

This section is the reference for what each step configures and why. For a guided, numbered walkthrough from a fresh install through your first published page, see [docs/installation.md](docs/installation.md) instead - the Quick start above is the fastest path if you just want to confirm the package works.

```bash
composer require wearepixel/inlay
php artisan inlay:install
```

### `inlay:install`

`inlay:install` is the fastest path to a working install. It:

1. Publishes `config/inlay.php` (equivalent to `php artisan vendor:publish --tag=inlay-config`).
2. Runs `php artisan migrate`, creating the `pages`, `page_sections`, and `page_revisions` tables.
3. Prints the remaining manual steps (registering routes, setting middleware, registering your first block).

You can run either step individually if you'd rather control migrations yourself:

```bash
php artisan vendor:publish --tag=inlay-config
php artisan migrate
```

### Registering routes

Inlay registers **no routes on its own, of any kind**. You opt into each part explicitly, from your own `routes/web.php`:

```php
// routes/web.php
use WeArePixel\Inlay\Facades\Inlay;

Inlay::adminRoutes();          // the admin - page index, editor, preview
Inlay::pageRoutes();           // published Pages, served publicly by Inlay itself
Inlay::routes();               // both of the above, as a convenience
```

`adminRoutes()` registers the admin under `config('inlay.admin.prefix')` (`inlay` by default, so `/inlay/pages`, `/inlay/pages/create`, and so on) with route names prefixed `inlay.admin.`. A bare visit to the prefix root itself (e.g. `/inlay`) redirects to the page index rather than 404ing. `pageRoutes()` is what makes a published Page reachable at a real public URL with no controller of your own - see [Public routing](#public-routing) below and [docs/routing.md](docs/routing.md) for the full reference (prefixes, nested paths, the homepage, route ordering). Most applications only need `adminRoutes()` plus either `pageRoutes()` **or** their own routes calling `Inlay::renderPage()` - rarely both, and never neither.

### Configuring admin middleware

`config('inlay.admin.middleware')` controls what guards the admin. The default is:

```php
'admin' => [
    'middleware' => ['web'],
],
```

**This is not a production authentication configuration.** With nothing else added, every route `Inlay::adminRoutes()` registers - including deleting a page - is public to anyone who can reach your application. At minimum, add your own authentication guard:

```php
'admin' => [
    'middleware' => ['web', 'auth'],
],
```

Add authorization too if not every authenticated user should have admin access, e.g. `['web', 'auth', 'can:manage-pages']`. See [docs/installation.md](docs/installation.md) for a worked example. This is entirely separate from `config('inlay.pages.middleware')`, which guards public Page requests instead - a visitor never needs an admin session to view a published Page, and an admin session is never required to serve one.

### Why Livewire middleware persistence matters

The admin's every action after the first page load - adding a block, editing a setting, publishing - is dispatched to Livewire's own `/livewire/update` endpoint, not back through `Inlay::adminRoutes()`. Middleware that isn't explicitly told to persist across that boundary would otherwise guard only the _initial_ page load, leaving every subsequent action unprotected for a session that's since been revoked or expired.

`Inlay::adminRoutes()` handles this for you: it resolves `config('inlay.admin.middleware')` to concrete middleware classes and registers them with Livewire as [persistent middleware](https://livewire.laravel.com/docs/security#middleware-persistence-with-hierarchical-routing), so they re-run on every subsequent admin action too. You don't need to configure this separately - just make sure your real middleware is in `config('inlay.admin.middleware')` before `Inlay::adminRoutes()` runs.

### Configuring `Inlay::previewView()`

The editor's preview pane is a real `<iframe>` pointed at your application's actual frontend - never a recreation of it inside the admin. Tell Inlay which of your own views should render a composed page:

```php
// AppServiceProvider::boot(), or anywhere that runs before a preview request
use WeArePixel\Inlay\Facades\Inlay;

Inlay::previewView('inlay-previews.default');
```

```blade
{{-- resources/views/inlay-previews/default.blade.php --}}
<x-app-layout>
    {{ $composition }}
</x-app-layout>
```

Your view receives:

- `$page` - the `WeArePixel\Inlay\Models\Page` being previewed.
- `$composition` - an `Illuminate\Contracts\Support\Htmlable` containing the rendered blocks. Echo it directly (`{{ $composition }}` is safe - it's already-rendered HTML, not raw user input).
- `$context` - reserved for future use; currently always `null`.

With no `previewView()` configured, the preview falls back to a bare, clearly-labeled unstyled wrapper rather than silently pretending to be your real design.

## Registering blocks

A block is any Blade class component that implements the marker interface `WeArePixel\Inlay\Contracts\InlayBlock`:

```php
namespace App\Inlay\Blocks;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Contracts\InlayBlock;

final class Hero extends Component implements InlayBlock
{
    public function __construct(
        public string $heading = 'Welcome',
    ) {}

    public function render(): View
    {
        return view('blocks.hero');
    }
}
```

Register it against a handle in `config/inlay.php`:

```php
'blocks' => [
    'hero' => \App\Inlay\Blocks\Hero::class,
],
```

The handle (`hero`) is what gets persisted against a page's sections - never the class name directly, so renaming or moving the class doesn't break existing pages.

### Categories

The block picker groups blocks by category, using an optional `#[BlockMeta]` class attribute:

```php
use WeArePixel\Inlay\Attributes\BlockMeta;

#[BlockMeta(
    label: 'Hero',
    category: 'Headers',
    description: 'A large banner with a heading and optional call to action.',
    icon: 'layout-header',
)]
final class Hero extends Component implements InlayBlock
{
    // ...
}
```

A block with no `#[BlockMeta]` still registers and renders normally - the picker falls back to a label derived from its handle and an "Uncategorised" group.

### Picker thumbnails

`icon` (above) is a short string shown as a letter-tile fallback; for a real visual preview in the block picker, set `thumbnail` to a public URL instead:

```php
#[BlockMeta(
    label: 'Hero',
    category: 'Headers',
    thumbnail: asset('images/blocks/hero.png'),
)]
```

A block with a `thumbnail` shows that image in its picker card; one with neither `thumbnail` nor `icon` falls back to the first letter of its label. There's no required size or aspect ratio - a small screenshot of the block's own rendered output works well.

### Single-file blocks

For a simple block, a class + view is more ceremony than it needs - declare it in one Blade file instead:

```blade
{{-- resources/views/inlay/blocks/hero.blade.php --}}
<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('hero')
        ->name('Hero')
        ->category('Headers')
        ->settings(Settings::make()->text('heading')->label('Heading')->default('Welcome'))
);
?>
<section><h1>{{ $heading }}</h1></section>
```

```php
Inlay::singleFileBlock(resource_path('views/inlay/blocks/hero.blade.php'));
// or register every file in a directory at once:
Inlay::singleFileBlocks(resource_path('views/inlay/blocks'));
```

It compiles into the exact same internal representation a class + view block does - the registry, picker, settings resolution, rendering, publishing, and revisions treat the two identically. This is not Livewire Volt: it's an ordinary Blade file that may itself contain nested Livewire tags, not a Livewire component. See [docs/blocks.md#single-file-blocks](docs/blocks.md#single-file-blocks) for the full authoring reference, including why every setting needs an explicit `->default(...)` here (there's no constructor for it to come from instead).

## Authoring settings

`InlayBlock` is deliberately an empty marker interface - it imposes no required method, because a block's settings can be declared two different ways, and neither should be forced on the other.

### Attribute authoring

Declare a setting directly on the constructor parameter it maps to:

```php
use WeArePixel\Inlay\Attributes\Select;
use WeArePixel\Inlay\Attributes\Text;

final class Hero extends Component implements InlayBlock
{
    public function __construct(
        #[Text(label: 'Heading', maxLength: 80)]
        public string $heading = 'Welcome',

        #[Select(label: 'Image size')]
        public ImageSize $imageSize = ImageSize::Large,
    ) {}

    // ...
}
```

Attributes exist for every field type: `Text`, `Textarea`, `Richtext`, `Number`, `Toggle`, `Select`, `Image`, `Link`. There is no attribute for `Repeater` or `Group` - those are fluent-only (see below), since a repeatable item schema doesn't map onto a single constructor parameter.

### Fluent `Settings` authoring

Declare a static `settings()` method returning a `Settings` definition instead, useful for repeaters, groups, or when you'd rather keep field configuration out of the constructor signature:

```php
use WeArePixel\Inlay\Schema\Settings;

final class FeaturedCollection extends Component implements InlayBlock
{
    public function __construct(
        public string $heading = 'Featured',
        public array $productIds = [],
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()
            ->text('heading')->label('Heading')
            ->repeater('productIds', Settings::make()->text('id')->label('Product ID'))
                ->label('Products');
    }

    public function render(): View
    {
        return view('blocks.featured-collection', [
            'products' => Product::query()->whereIn('id', array_column($this->productIds, 'id'))->get(),
        ]);
    }
}
```

`FeaturedCollection` here reads real application data (`Product::query()`) inside `render()` - settings only ever carry the _reference_ (which product IDs), never the data itself, so the rendered output always reflects the current state of your own database.

### Mixing attributes and fluent definitions

A block can use both. When the same setting name is declared both ways, **the fluent `settings()` declaration wins** - attributes fill in anything fluent didn't already declare. This lets you use attributes for the common case and drop into `settings()` only for the one field that needs a repeater or a hand-tuned option list, without restating everything else fluently.

### Constructor defaults are exclusively authoritative

For a top-level setting, the constructor parameter's own default value and nullability are the **only** source of truth - there is no schema-level `.default()` escape hatch at the top level. A required, non-nullable constructor parameter with no default fails registration immediately, with a clear exception naming the block and the setting. `.default()` is only valid on a field nested inside a repeater or group item schema, which has no constructor parameter to be authoritative instead.

### Enums

A `Select` setting backed by a PHP backed enum gets its options derived automatically - you don't hand-write an options array:

```php
enum ImageSize: string
{
    case Small = 'small';
    case Large = 'large';
}
```

The package persists the enum's scalar `value`, hydrates the matching case before constructing your block, and validates that a stored value corresponds to a real case. If you want a label other than the case name's title-cased form, implement `WeArePixel\Inlay\Support\SettingOptions`:

```php
enum ImageSize: string implements \WeArePixel\Inlay\Support\SettingOptions
{
    case Small = 'small';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small (400px)',
            self::Large => 'Large (1200px)',
        };
    }
}
```

An orphaned stored value (a case that's since been removed from the enum) falls back to the constructor default and logs a warning, uniformly wherever settings are resolved - public rendering, the admin editor, and publishing. There is currently no separate, more visible admin-facing warning and no publish-time block for this case - see [Current limitations](#current-limitations).

### `LinkValue`

The `Link` field type hydrates into `WeArePixel\Inlay\Support\LinkValue`, a plain value object:

```php
use WeArePixel\Inlay\Attributes\Link;
use WeArePixel\Inlay\Support\LinkValue;

final class Hero extends Component implements InlayBlock
{
    public function __construct(
        #[Link(label: 'Call to action')]
        public ?LinkValue $cta = null,
    ) {}
}
```

```blade
@if ($cta)
    <a href="{{ $cta->url }}" @if ($cta->openInNewTab) target="_blank" @endif>{{ $cta->label }}</a>
@endif
```

Only `http://`, `https://`, `mailto:`, and relative URLs are ever persisted - the package rejects other schemes (e.g. `javascript:`) before the value reaches storage, so you can render `$cta->url` into an `href` without adding your own scheme check.

### `ImageValue`

The `Image` field type hydrates into `WeArePixel\Inlay\Support\ImageValue`, not the bare disk-relative path the editor stores - your block never has to resolve `Storage::disk(...)->url(...)` itself. `ImageValue::__toString()` resolves the real URL, so echoing it directly is correct:

```php
use WeArePixel\Inlay\Attributes\Image;
use WeArePixel\Inlay\Support\ImageValue;

final class Hero extends Component implements InlayBlock
{
    public function __construct(
        #[Image(label: 'Photo')]
        public ?ImageValue $photo = null,
    ) {}
}
```

```blade
@if ($photo)
    <img src="{{ $photo }}" alt="">
@endif
```

Type the parameter `?ImageValue $photo = null` if "no image" should behave like a genuinely absent value - `@if ($photo)` then works as expected. A non-nullable `ImageValue $photo = new ImageValue()` still gets a real instance with nothing uploaded (PHP objects are always truthy) - check `$photo->isEmpty()` explicitly in that case rather than `@if ($photo)`.

### Settings that conditionally affect Blade markup

`visibleWhen()`/`hiddenWhen()` control whether a field appears in the settings inspector - they are a UI convenience only, never a security boundary (an undeclared or hidden setting can never be persisted regardless):

```php
public static function settings(): Settings
{
    return Settings::make()
        ->toggle('showCta')->label('Show call to action')
        ->text('ctaLabel')->label('Button label')->visibleWhen('showCta', true);
}
```

Your own Blade markup makes the same decision independently, using the resolved property value:

```blade
@if ($showCta)
    <a href="#">{{ $ctaLabel }}</a>
@endif
```

### Presentation stays host-owned

Inlay never generates or injects markup into your block's output. Everything your block's Blade view renders - classes, structure, conditional wrappers - is exactly what you wrote. A settings value can change _which_ branch your view takes (see above), but the package has no mechanism for an editor to add arbitrary CSS classes or markup of their own.

### Nested Blade and Livewire usage

A registered block is always a normal Blade class component - it may freely contain Livewire, Alpine, or any other host-owned frontend behaviour in its own view, with zero special handling required:

```php
final class ContactSection extends Component implements InlayBlock
{
    public function __construct(
        #[Text(label: 'Heading')]
        public string $heading = 'Get in touch',
    ) {}

    public function render(): View
    {
        return view('blocks.contact-section');
    }
}
```

```blade
{{-- resources/views/blocks/contact-section.blade.php --}}
<section>
    <h2>{{ $heading }}</h2>
    <livewire:contact-form />
</section>
```

The nested `<livewire:contact-form />` mounts and hydrates exactly as it would anywhere else in your application - Inlay has no awareness of it and needs none.

### Common registration errors and their messages

| Situation                                                                                | Exception                                                                                  |
| ---------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| A `settings()`/attribute entry names a field with no matching constructor parameter      | `Block [...] declares a setting [...] with no matching constructor parameter.`             |
| A required, non-nullable constructor parameter has no default                            | `Block [...] setting [...] has no constructor default and is not nullable. ...`            |
| `->options(EnumClass::class)` doesn't match the constructor parameter's actual enum type | `Block [...] setting [...] declares options for [...], but the constructor expects [...].` |
| The same setting name is declared twice in one `Settings` definition                     | `Duplicate setting [...] declared twice in the same settings definition.`                  |
| A `Settings` modifier (`->label()`, `->required()`, etc.) is called before any field     | `...() must follow a field definition.`                                                    |

## Working with pages

### Adding and editing a page

From `/inlay/pages`, create a page (title, and optionally a public path, meta title/description, homepage status, and - if you've registered more than one - a Page type), then compose it in the editor: open the block picker, search or browse by category, add a block, select it in the tree to edit its settings in the inspector, and watch the preview iframe reload. Page-level fields (title, path, metadata, homepage) are edited separately from block settings, via **Page Settings** in the editor's header - see [docs/editor.md](docs/editor.md) for the full editor-facing workflow.

### Page Definitions

By default any Page may contain any registered block, in any order. If you need to guarantee a page's structure - a Collection Page that must always start with a hero and a product grid it can't remove, say - register a **Page Definition**: a developer-authored policy object that restricts which blocks a kind of page may contain, which are required or locked, and what a new page of that kind starts with. Enforced both in the editor (picker filtering, tree indicators, guarded add/delete/reorder) and at publish time (blocking a page that violates it). See [docs/page-definitions.md](docs/page-definitions.md) for the full reference and a worked ecommerce example.

### Draft and publish behaviour

Every edit writes immediately to the page's **draft** state - there is no separate save step. Draft edits never affect what's publicly rendered until you explicitly publish. Publishing resolves every block's fully-resolved effective settings (defaults merged with your overrides) and snapshots them into a new revision; a later change to a block's own constructor default can never silently alter an already-published revision, because the snapshot is self-contained. Publishing is blocked while a page violates its Page Definition (see above).

### Revisions and restore

Every publish creates a new, immutable revision. The editor's version history lists them. Restoring one always replaces the page's live draft sections with that revision's snapshot; whether that's _also_ an immediate publish depends on whether the restored content satisfies the page's **current** Page Definition (which can have changed since that revision was published) - if it does, restoring publishes a new revision from it (labeled `Restored from revision #N`), exactly as before; if it doesn't, the content lands in the draft only, and publishing stays blocked until it's corrected.

## Rendering on your site

Inlay never registers public routes automatically. You get to choose, per Page, between two integration styles - and can mix both in the same application:

- **Automatic public Pages**, via `Inlay::pageRoutes()` - Inlay resolves the path, loads the published Page, and renders your configured view. No route or controller of your own needed. See [Public routing](#public-routing) below.
- **Application-owned routes**, rendering a Page through routes your application already owns - via `Inlay::renderPage()` or `<x-inlay::render>`. Use this when a Page needs data or context your route already has (the current tenant, a resolved model, an A/B test bucket) that Inlay's own routing has no way to supply.

A Page with no public `path` and not set as the homepage is intentionally host-rendered only - it never resolves through `Inlay::pageRoutes()`, by design.

### Public routing

```php
// routes/web.php
Inlay::pageRoutes(prefix: 'pages'); // -> /pages/{path}, e.g. /pages/about
```

Stored path `test/test` with `prefix: 'pages'` resolves at `/pages/test/test`. **The configured prefix is not part of the Page's stored path. It is added only when routing or generating the public URL** - changing the prefix changes every Page's URL immediately, without touching a single Page row. See [docs/routing.md](docs/routing.md) for the full reference: root vs. prefixed routing, nested paths, the homepage, route registration order, and `Inlay::url()`.

The configured view receives the same three values as `previewView()`:

```blade
{{-- resources/views/inlay/pages/show.blade.php --}}
<x-app-layout>
    <title>{{ $page->effectiveMetaTitle() }}</title>
    @if ($page->effectiveMetaDescription())
        <meta name="description" content="{{ $page->effectiveMetaDescription() }}">
    @endif

    {{ $composition }}
</x-app-layout>
```

Inlay never injects `<title>`/`<meta>` tags into your layout itself - your view is responsible for echoing `effectiveMetaTitle()`/`effectiveMetaDescription()` wherever your own `<head>` expects them. `effectiveMetaTitle()` falls back to the Page's title when no meta title is set; `effectiveMetaDescription()` returns `null` when none is set (nothing is auto-generated from block content).

### Embedded rendering

Render a Page's latest published revision from a route your own application already owns, with `Inlay::renderPage()` or the `<x-inlay::render>` Blade component:

```php
// A route or controller your application already owns
Route::get('/{page:path}', function (\WeArePixel\Inlay\Models\Page $page) {
    return view('pages.show', ['page' => $page]);
});
```

```blade
{{-- resources/views/pages/show.blade.php --}}
<x-app-layout>
    <x-inlay::render :page="$page" :context="['collection' => $collection ?? null]" />
</x-app-layout>
```

`$context` is optional, host-supplied data with no schema Inlay understands - merged underneath each block's own resolved settings, so a block whose own Blade view declares a matching property picks it up the same way it already picks up an ordinary container-resolvable dependency. Inlay never inspects or validates it. A page with no published revision renders nothing - there's no draft leakage onto a public route.

### Frontend styling neutrality

The package owns composition; your application owns presentation, completely. Inlay never loads Tailwind, Bootstrap, or any CSS framework into your public pages - `<x-inlay::render>` and `Inlay::pageRoutes()` both output exactly what your own block views render, in whatever styling system your application already uses. The admin's own design (loaded only under `Inlay::adminRoutes()`'s prefix) is entirely separate, self-hosted, and never touches your public output.

### Nested Livewire inside an Inlay block

Covered above under [Nested Blade and Livewire usage](#nested-blade-and-livewire-usage) - the same rule applies identically whether the block is rendered by the admin's preview or by `<x-inlay::render>` on a real page: it's just Blade, so nested Livewire just works.

## Current limitations

This release deliberately does not include:

- Navigation/menu building.
- A media library (the `image` field type is a single file upload; there's no asset browser).
- Rich-text editing - `richtext` is presently a plain textarea. The package does not sanitize this field's HTML: if your block renders it with `{!! !!}`, whatever an editor enters is output as-is. Only give editor access to people you'd trust with that.
- Nested blocks, slots/regions, or reusable "global" blocks. Composition is a flat, ordered list rendered inside a layout your own route or controller already provides.
- Maximum/minimum block instance counts, locking or requiring one specific instance rather than every instance of a block, conditional allow-lists, role-specific Page Definitions, editor-built Page Definitions, and reassigning a Page's type after creation - see [docs/page-definitions.md#scope](docs/page-definitions.md#scope).
- Additional rendering adapters. There is exactly one renderer: a real Blade component tag, resolved through a registered alias. A "directly registered Livewire/Vue/React outer component" is not, and will not be, supported the same way a Blade component is.
- A distinct, more visible admin-facing warning for an orphaned enum value, and a publish-time block for it. It currently falls back to the constructor default and logs a warning, the same as public rendering.
- Settings versioning/migration for a block whose settings shape has changed since content was authored against it.

## Development setup

```bash
git clone <this repository>
cd inlay
composer install
```

Browser tests additionally need Node and Playwright's browser binary (see [Running tests](#running-tests)):

```bash
npm install
npx playwright install chromium
```

## Running tests

```bash
composer test              # the full Pest suite (Feature + Unit)
composer test:browser      # real-browser tests (tests/Browser) - slower, needs Playwright
composer analyse           # PHPStan/Larastan, level 6
composer format            # Laravel Pint
```

## Building admin assets

There is no build step. The admin's CSS and JavaScript (`resources/dist/inlay.css`, `resources/dist/inlay.js`) are hand-authored plain files, served directly by the package through its own asset route - no bundler, no CDN dependency, nothing to compile. Edit them directly; there's nothing to run afterward.
