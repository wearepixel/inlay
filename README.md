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

## CSS framework

Tell Inlay which CSS approach the site uses. It picks the starter kit variant and tells AI agents what to build with:

```dotenv
INLAY_CSS_FRAMEWORK=tailwind   # tailwind, bootstrap or plain
```

Inlay reads the classes your site actually defines from its compiled CSS, and agent-authored blocks may use only those. The rest of `config('inlay.styling')`:

| Key | Default | Purpose |
|---|---|---|
| `vite_manifest` | `public/build/manifest.json` | Where the compiled CSS is found. |
| `vite_entries` | all CSS entries | Limit to the entries public pages load, e.g. `['resources/css/app.css']`, so admin themes (Filament) are excluded. |
| `stylesheets` | `[]` | Extra compiled CSS files, for sites not built with Vite. |
| `extra_classes` | `[]` | Classes that exist only as JavaScript hooks. |
| `denied_classes` | `['fixed']` | Never allowed, even when defined - `fixed` would let a block overlay the page. |

Run `npm run build` after changing CSS: until then the new classes are not in the compiled files.

## Landing page starter kit

```bash
php artisan inlay:install --starter=landing              # Tailwind v4 utilities
php artisan inlay:install --starter=landing --css=plain  # framework-free CSS, for Bootstrap or hand-written CSS
```

`--css` defaults to `tailwind` when `INLAY_CSS_FRAMEWORK=tailwind`, and `plain` otherwise. Either variant copies ten blocks (hero, logo cloud, feature grid, text and image, stats, testimonials, pricing, FAQ, call to action, rich text) into `resources/views/inlay/blocks` and a `LandingPage` page type into `app/Inlay`. Existing files are kept unless you pass `--force`. Register them in a service provider's `boot()`:

```php
Inlay::singleFileBlocks(resource_path('views/inlay/blocks'));
Inlay::pageDefinition(\App\Inlay\LandingPage::class);
```

- **Tailwind:** the blocks use v4 syntax. Tailwind v4 scans `resources/views` automatically; if your setup does not, add `@source "../views/inlay";` to your CSS.
- **Plain:** the blocks use `inlay-` prefixed classes and the command also copies `resources/css/inlay.css`. Add `@import "./inlay.css";` to your main stylesheet and rebrand it by overriding the `--inlay-*` variables.

The blocks are yours from then on - restyle them to the client's brand.

## Setting up a new project

1. `composer require wearepixel/inlay "livewire/livewire:^4.0"`, then `php artisan inlay:install --starter=landing`.
2. Register the starter blocks and page type (above), and set `INLAY_CSS_FRAMEWORK`.
3. In `routes/web.php`: `Inlay::adminRoutes()`, `Inlay::previewLinkRoutes()` and, to serve pages, `Inlay::pageRoutes()` last.
4. Guard the admin with `config('inlay.admin.middleware')`, e.g. `['web', 'auth']`.
5. Call `Inlay::previewView('your.preview.view')` so previews use your layout and assets.
6. `npm run build`, then visit `/inlay/pages`.
7. For AI agents: `composer require laravel/mcp`, mount the server and define the abilities (below).

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

To recreate a design such as a Figma file, an agent sometimes needs a block that does not exist yet. With `INLAY_AGENT_BLOCKS=true` (and the migrations run), MCP users with the `inlay.author-blocks` ability (and `inlay.manage-pages`) can create blocks with `inlay-save-block`. These are declarative - a JSON element tree, never Blade or PHP - and live as soon as they are saved, with no deploy.

An agent block may only use classes your compiled CSS defines (see [CSS framework](#css-framework)), so on a Tailwind site the agent can use exactly the utilities your build contains - no arbitrary values, no other framework, no inline styles. `inlay-get-styling` gives the agent that list and your design tokens. To make more utilities available, safelist them in your CSS (`@source inline(...)` in Tailwind v4) and rebuild. Changing or deleting a block that a published page uses needs publish rights.

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
