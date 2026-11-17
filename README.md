# Inlay

A Laravel-native visual composition layer for existing applications. A developer registers ordinary Blade components as sections; Inlay gives the people who work with content a packaged admin to compose pages from them, edit their settings, preview against the real frontend, and publish - without the developer building any admin UI of their own.

To learn all about it, head over to the [extensive documentation](https://wearepixel.com.au/docs/inlay/v1/introduction).

Supported Laravel versions: 11, 12, and 13. Requires PHP 8.2+.

> Inlay is pre-1.0. The public API is the current, intended shape, but this is not yet a stable release.

## Installation

```bash
composer require wearepixel/inlay "livewire/livewire:^4.0"
php artisan inlay:install
```

`inlay:install` looks at your app - Tailwind, Bootstrap or plain CSS, whether it has a login, which layouts it already has - and wires Inlay in for you:

- publishes the config and runs the migrations
- writes `app/Providers/InlayServiceProvider.php`, where sections, blocks and the "who may edit pages" gate live (local only until you change it)
- writes the view published pages render through, inside one of your layouts or as a standalone page loading your Vite assets
- adds the admin and page routes to `routes/web.php` (in a brand new app, replacing the welcome route)
- guards the admin, requiring a login when your app has one
- optionally copies a starter kit and builds a homepage from it (`--demo`)

Every step is skipped when it is already done, so it is safe to run again. Then:

```bash
npm run build
php artisan inlay:doctor   # checks the setup and tells you how to fix anything missing
```

and visit `/inlay/pages`. Livewire is needed for the admin only - rendering a page never touches it.

### Adding Inlay to an existing project

Run the same command; it leaves your routes and welcome page alone, and asks which of your layouts published pages should render inside. To turn pages you already have into editable ones:

```bash
php artisan inlay:import resources/views/pages/about.blade.php --path=about
```

Each top-level element becomes a section in `resources/views/inlay/sections`, with its headings, paragraphs, links and images lifted into settings (current content as the defaults), and a draft page is built from them. Anything already using Blade (`{{ $user->name }}`, `@if`) is left as written. Review the generated files - rename settings, put back text that should not be editable - then edit and publish the page. `--dry-run` shows what would be created.

## CSS framework

Tell Inlay which CSS approach the site uses. It picks the starter kit variant and tells AI agents what to build with:

```dotenv
INLAY_CSS_FRAMEWORK=tailwind   # tailwind, bootstrap or plain
```

Inlay reads the classes your site actually defines from its compiled CSS, and agent-authored sections may use only those. The rest of `config('inlay.styling')`:

| Key | Default | Purpose |
|---|---|---|
| `vite_manifest` | `public/build/manifest.json` | Where the compiled CSS is found. |
| `vite_entries` | all CSS entries | Limit to the entries public pages load, e.g. `['resources/css/app.css']`, so admin themes (Filament) are excluded. |
| `stylesheets` | `[]` | Extra compiled CSS files, for sites not built with Vite. |
| `extra_classes` | `[]` | Classes that exist only as JavaScript hooks. |
| `denied_classes` | `['fixed']` | Never allowed, even when defined - `fixed` would let a section overlay the page. |

Run `npm run build` after changing CSS: until then the new classes are not in the compiled files.

## Starter kits

```bash
php artisan inlay:install --starter=landing    # hero, features, pricing, FAQ and more
php artisan inlay:install --starter=showcase   # bento grid, layered hero, logo marquee, testimonial wall, pricing comparison, timeline
```

The **landing** kit comes in Tailwind v4, Bootstrap 5 and framework-free plain CSS (`--css=tailwind|bootstrap|plain`, detected from `package.json` by default), with eleven sections and four blocks (heading, text, button, image). The **showcase** kit is a set of more complex Tailwind layouts to start from. Each kit also adds a page type to `app/Inlay`. Existing files are kept unless you pass `--force`.

- **Tailwind:** v4 syntax. Tailwind v4 scans `resources/views` automatically; if your setup does not, add `@source "../views/inlay";` to your CSS.
- **Bootstrap:** Bootstrap 5.3 classes only, no extra CSS.
- **Plain:** `inlay-` prefixed classes, plus `resources/css/inlay.css` - add `@import "./inlay.css";` to your main stylesheet and rebrand it with the `--inlay-*` variables.

The sections and blocks are yours from then on - restyle them to the client's brand.

## Sections and blocks

A page is a list of **sections** - full-width bands such as a hero or a pricing table. A section can also have a **blocks** area that editors fill with smaller pieces (a heading, some text, a button) in any order. You build the sections and blocks in Blade with your own CSS; editors arrange them.

A section is a Blade file that declares its settings at the top:

```blade
<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('feature')
        ->name('Feature')
        ->settings(Settings::make()
            ->text('heading')->label('Heading')->default('Why it matters')
            ->blocks('content')->label('Content')->allow(['text', 'button'])->max(6)->default([
                ['type' => 'text', 'settings' => ['body' => '<p>Start here.</p>']],
            ]))
);
?>
<section class="py-24">
    <h2>{{ $heading }}</h2>
    {{ $content }}
</section>
```

A block is declared the same way with `block(Block::make('button')->...)`, in `resources/views/inlay/blocks`. Blocks cannot contain blocks.

- `{{ $content }}` renders every block in order. To wrap each one, loop: `@foreach ($content as $block) <div class="card">{{ $block }}</div> @endforeach` - each item also exposes `$block->type` and `$block->settings`.
- `->allow([...])` limits which blocks the area accepts (all registered blocks when omitted); `->max(n)` caps how many.
- Blocks are stored inside the section's settings, so drafts, publishing, version history and restore cover them with no extra work. A block whose type is later unregistered is skipped when rendering.
- Class-based blocks work like class-based sections: implement `InlayBlock`, describe it with `#[BlockMeta]`, and list it in `config('inlay.blocks')`.

In the editor, hover over the preview to outline a section or block and click it to edit it. Picking a section from the list scrolls the preview to it.

## Your own admin

Inlay's admin can live inside the host's own admin panel instead of its own shell.

**Your layout, Inlay's screens.** Point `config('inlay.admin.layout')` at a Blade component that renders `$slot` (and optionally `$title`). Inlay's pages, editor and settings render inside it, on Inlay's routes:

```php
'admin' => [
    'prefix' => 'admin/content',
    'middleware' => ['web', 'auth'],
    'ability' => 'manage-content',
    'layout' => 'layouts.admin',   // resources/views/components/layouts/admin.blade.php
],
```

The editor fills the height of the element around it, so give the layout's content area a height (for example a flex column with `min-height: 100dvh`).

**Your routes, Inlay's components.** Mount the components yourself, wrapped in `<x-inlay::frame>` (which loads Inlay's CSS and JS and scopes its styles):

```blade
<x-layouts.admin>
    <x-inlay::frame>
        <livewire:inlay::page-editor :page="$page" />
    </x-inlay::frame>
</x-layouts.admin>
```

Available: `inlay::page-index`, `inlay::page-create`, `inlay::page-editor` and `inlay::site-settings-editor`. Keep `Inlay::adminRoutes()` registered - the editor's preview and the admin assets are served from it. Point Inlay's own links at your routes:

```php
Inlay::adminUrlsUsing(fn (string $screen, mixed ...$params) => match ($screen) {
    'pages.edit' => route('admin.content.edit', $params[0]),
    default => null, // keep Inlay's route
});
```

**Access.** `config('inlay.admin.ability')` is checked by every admin screen on the first load and on every action after it, receiving the Page where there is one. Set it whenever you mount the components on your own routes: Livewire only re-runs the middleware Inlay registers for its own routes.

**Styling.** Override the `--inlay-*` custom properties (see the top of `resources/dist/inlay.css`) to match your brand:

```css
:root { --inlay-accent: #334735; --inlay-bg: #f8fafc; --inlay-font: "Almarai", sans-serif; }
```

## Site settings

Content that belongs to the whole site - contact details, header links, footer text - is declared with the same `Settings` schema as a section and edited under **Settings** in the admin:

```php
use WeArePixel\Inlay\Schema\Settings;

Inlay::siteSettings('contact', Settings::make()
    ->text('phone')->label('Phone')->default('+61 400 000 000')
    ->text('email')->label('Email')
    ->textarea('address'), label: 'Contact details');
```

Read values anywhere, hydrated like section settings (images to `ImageValue`, links to `LinkValue`):

```blade
<a href="tel:{{ Inlay::setting('contact.phone') }}">{{ Inlay::setting('contact.phone') }}</a>
@foreach (Inlay::setting('header.links', []) as $link) ... @endforeach
```

Site settings are live as soon as they are saved: there is no draft or publish step. Run `php artisan migrate` after upgrading - they are stored in `inlay_site_settings`.

## MCP server

With `laravel/mcp` installed (Laravel 12+), AI agents can list sections, compose pages, preview them and, if allowed, publish. Either give Inlay its own endpoint:

```php
// routes/ai.php
Mcp::web('/mcp/pages', \WeArePixel\Inlay\Mcp\InlayServer::class)
    ->middleware(['auth:sanctum']);
```

or add its tools to an MCP server you already run, alongside your own:

```php
use WeArePixel\Inlay\Mcp\InlayTools;

protected array $tools = [MyTool::class, ...InlayTools::TOOLS];
protected array $resources = [...InlayTools::RESOURCES];
```

Every tool name starts with `inlay-`, and every tool authorises the request itself, so mounting them in a server with a wider audience does not widen who can edit pages:

- `inlay.manage-pages` for everything. Tools that act on a page pass it to the ability, and tools that don't (listing sections, creating a page) pass nothing, so declare it optional: `Gate::define('inlay.manage-pages', fn (User $user, ?Page $page = null) => ...)`.
- `inlay.publish-pages` plus `config('inlay.mcp.allow_publish') === true` to publish. Publishing is off by default.

A section's blocks area is filled the same way as any other setting - a list of `{"type": ..., "settings": {...}}` - and `inlay-list-sections` describes which blocks each area allows and their settings.

Spatie permissions with those names work as they are. Agents get feedback after every change: what each section renders (headings, links, images, text), content still on section defaults, publish blockers and a signed preview link. To enable preview links, call `Inlay::previewLinkRoutes()` from `routes/web.php`. Links are built from `APP_URL` (never the request's Host header) and their payload is held in the default cache store, which must be shared between processes - not `array`.

### Agent-authored sections

To recreate a design such as a Figma file, an agent sometimes needs a section that does not exist yet. With `INLAY_AGENT_SECTIONS=true` (and the migrations run), MCP users with the `inlay.author-sections` ability (and `inlay.manage-pages`) can create sections with `inlay-save-section`. These are declarative - a JSON element tree, never Blade or PHP - and live as soon as they are saved, with no deploy.

An agent section may only use classes your compiled CSS defines (see [CSS framework](#css-framework)), so on a Tailwind site the agent can use exactly the utilities your build contains - no arbitrary values, no other framework, no inline styles. `inlay-get-styling` gives the agent that list and your design tokens. To make more utilities available, safelist them in your CSS (`@source inline(...)` in Tailwind v4) and rebuild. Changing or deleting a section that a published page uses needs publish rights.

For desktop and mobile screenshots in `inlay-preview-page`, install `spatie/browsershot` (with Puppeteer) and set `INLAY_SCREENSHOT_DRIVER=browsershot`, or bind your own `WeArePixel\Inlay\Contracts\Screenshotter`. The renderer must be able to reach `APP_URL`. Clients that support MCP Apps also show the preview inline.

## Documentation

Full documentation - registering sections, authoring settings, Page Definitions, public routing, the editor workflow, and the current limitations - is available at [wearepixel.com.au/docs/inlay](https://wearepixel.com.au/docs/inlay/v1/introduction).

## Development setup

```bash
git clone <this repository>
cd inlay
composer install
```

Browser tests additionally need Node and Playwright's browser binary:

```bash
npm install
npx playwright install chromium
```

## Running tests

```bash
composer test              # the full Pest suite (Feature + Unit)
composer test:browser      # real-browser tests (tests/Browser) - slower, needs Playwright
composer test:no-livewire  # installs the package into a Livewire-free host and proves it boots and renders
composer analyse           # PHPStan/Larastan, level 6
composer format            # Laravel Pint
```

`composer test:no-livewire` covers what the Pest suite structurally cannot: Livewire is a `require-dev` dependency here, so `class_exists(Livewire\Livewire::class)` is always true and an eager reference to a Livewire class outside the admin path stays invisible. It builds a throwaway host in `fixtures/no-livewire/` that genuinely has no Livewire, then asserts the provider boots, a page publishes and renders, and `Inlay::adminRoutes()` throws without half-registering itself.

## Building admin assets

There is no build step. The admin's CSS and JavaScript (`resources/dist/inlay.css`, `resources/dist/inlay.js`) are hand-authored plain files, served directly by the package through its own asset route - no bundler, no CDN dependency, nothing to compile.

## License

The MIT License (MIT).
