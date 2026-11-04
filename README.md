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
