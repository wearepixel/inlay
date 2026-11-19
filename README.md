# Inlay

Editable pages for Laravel applications, built from your own Blade sections. You build the sections - the complex Tailwind or Bootstrap layouts - and the people who run the site compose pages from them, edit their content, preview against the real frontend, and publish, with every version kept.

To learn all about it, head over to the [extensive documentation](https://wearepixel.com.au/docs/inlay/v1/introduction).

Supported Laravel versions: 11, 12, and 13. Requires PHP 8.2+, and Livewire `^4.0` for the admin.

> Inlay is pre-1.0. The public API is the current, intended shape, but this is not yet a stable release.

## Installation

```bash
composer require wearepixel/inlay "livewire/livewire:^4.0"
php artisan inlay:install
npm run build
php artisan inlay:doctor
```

`inlay:install` detects your stack, wires the routes, views and admin guard, and can copy a starter kit of sections. In an existing project, `php artisan inlay:import resources/views/about.blade.php` turns a page you already have into editable sections. Then visit `/inlay/pages`.

## Documentation

Full documentation - sections and blocks, settings, starter kits, page definitions, routing, site settings, importing Blade pages, customising the admin and the MCP server - is available at [wearepixel.com.au/docs/inlay](https://wearepixel.com.au/docs/inlay/v1/introduction).

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
