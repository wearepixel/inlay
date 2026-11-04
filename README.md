# Inlay

A Laravel-native visual composition layer for existing applications. A developer registers ordinary Blade components as blocks; Inlay gives the people who work with content a packaged admin to compose pages from them, edit their settings, preview against the real frontend, and publish - without the developer building any admin UI of their own.

To learn all about it, head over to the [extensive documentation](https://wearepixel.com.au/docs/inlay/v1/introduction).

Supported Laravel versions: 11, 12, and 13. Requires PHP 8.2+.

> Inlay is pre-1.0. The public API is the current, intended shape, but this is not yet a stable release.

## Installation

Install the package through [Composer](http://getcomposer.org/):

```bash
composer require wearepixel/inlay
composer require "livewire/livewire:^4.0"
php artisan inlay:install
```

Livewire is required by the packaged admin only - rendering a composed page never touches it, so a host application that only renders can install Inlay with no Livewire at all. Then register a block, point `Inlay::adminRoutes()` at your own `routes/web.php`, and visit `/inlay/pages`. See [Installation](https://wearepixel.com.au/docs/inlay/v1/installation) for the full walkthrough, including admin middleware and the preview view.

## Landing page starter kit

```bash
php artisan inlay:install --starter=landing              # Tailwind v4 classes
php artisan inlay:install --starter=landing --css=plain  # framework-free CSS
```

Copies ten Tailwind blocks (hero, logo cloud, feature grid, text and image, stats, testimonials, pricing, FAQ, call to action, rich text) into `resources/views/inlay/blocks` and a `LandingPage` page type into `app/Inlay`. Existing files are kept unless you pass `--force`. Register them in a service provider:

```php
Inlay::singleFileBlocks(resource_path('views/inlay/blocks'));
Inlay::pageDefinition(\App\Inlay\LandingPage::class);
```

The blocks are yours from then on - restyle them to the client's brand. The Tailwind variant uses v4 syntax; make sure your Tailwind build scans `resources/views/inlay`. The plain variant (the default when `inlay.styling.framework` is not `tailwind`, e.g. a Bootstrap site) uses `inlay-` prefixed classes and copies `resources/css/inlay.css` - import it into your main stylesheet and rebrand it with the `--inlay-*` variables.

## MCP server

With `laravel/mcp` installed (Laravel 12+), AI agents can list blocks, compose pages, preview them and, if allowed, publish. Either give Inlay its own endpoint:

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

- `inlay.manage-pages` for everything. Tools that act on a page pass it to the ability, and tools that don't (listing blocks, creating a page) pass nothing, so declare it optional: `Gate::define('inlay.manage-pages', fn (User $user, ?Page $page = null) => ...)`.
- `inlay.publish-pages` plus `config('inlay.mcp.allow_publish') === true` to publish. Publishing is off by default.

Spatie permissions with those names work as they are. Agents get feedback after every change: what each section renders (headings, links, images, text), content still on block defaults, publish blockers and a signed preview link. To enable preview links, call `Inlay::previewLinkRoutes()` from `routes/web.php`. Links are built from `APP_URL` (never the request's Host header) and their payload is held in the default cache store, which must be shared between processes - not `array`.

### Agent-authored blocks

To recreate a design such as a Figma file, an agent sometimes needs a block that does not exist yet. With `INLAY_AGENT_BLOCKS=true` (and the migrations run), MCP users with the `inlay.author-blocks` ability can create blocks with `inlay-save-block`. These are declarative - a JSON element tree, never Blade or PHP - and live as soon as they are saved, with no deploy.

An agent block may only use classes your compiled CSS defines. Inlay reads them from the Vite manifest (`public/build/manifest.json`) plus any `inlay.styling.stylesheets`, so on a Tailwind site the agent can use exactly the utilities your build contains - no arbitrary values, no other framework, no inline styles. `inlay-get-styling` gives the agent that list and your design tokens. To make more utilities available, safelist them in your CSS (`@source inline(...)` in Tailwind v4) and rebuild. Set `INLAY_CSS_FRAMEWORK` (`tailwind`, `bootstrap` or `plain`) so agents know which approach the site uses.

For desktop and mobile screenshots in `inlay-preview-page`, install `spatie/browsershot` (with Puppeteer) and set `INLAY_SCREENSHOT_DRIVER=browsershot`, or bind your own `WeArePixel\Inlay\Contracts\Screenshotter`. The renderer must be able to reach `APP_URL`. Clients that support MCP Apps also show the preview inline.

## Documentation

Full documentation - registering blocks, authoring settings, Page Definitions, public routing, the editor workflow, and the current limitations - is available at [wearepixel.com.au/docs/inlay](https://wearepixel.com.au/docs/inlay/v1/introduction).

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
