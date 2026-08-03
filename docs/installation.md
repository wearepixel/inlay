# Installation guide

This walks a new Laravel developer from a fresh `composer require` through publishing their first page, using a `Hero` block as the running example. It assumes an existing Laravel 11, 12, or 13 application.

## 1. Install the package

```bash
composer require wearepixel/inlay
```

## 2. Run the installer

```bash
php artisan inlay:install
```

This publishes `config/inlay.php` and runs `php artisan migrate`, creating the `pages`, `page_sections`, and `page_revisions` tables. If you'd rather do these individually:

```bash
php artisan vendor:publish --tag=inlay-config
php artisan migrate
```

## 3. Register the admin routes

Add one line to your own `routes/web.php`, after your application's own routes:

```php
use WeArePixel\Inlay\Facades\Inlay;

Inlay::adminRoutes();
```

This registers the packaged admin under `/inlay` by default (`config('inlay.admin.prefix')`). Visit `/inlay/pages` and you should see an empty page index - there's nothing to compose yet, because no block is registered.

`Inlay::adminRoutes()` only registers the admin. If you also want Inlay to serve published Pages at a public URL with no route or controller of your own, add `Inlay::pageRoutes()` too (or call the combined `Inlay::routes()` instead of both) - see [docs/routing.md](routing.md). This guide covers the admin-only path first, since it's what every install needs regardless.

## 4. Set your admin middleware

Open `config/inlay.php`:

```php
'admin' => [
    'middleware' => ['web'],
],
```

**Stop here before going further if your application has real content or real users.** `['web']` alone means anyone who can reach your application can create, edit, and delete pages - there is no authentication or authorization built into the package itself, by design (the package is deliberately auth-agnostic; your application owns that decision). At minimum:

```php
'admin' => [
    'middleware' => ['web', 'auth'],
],
```

If your application uses Laravel's default `auth` guard and has a `login` named route, this is enough to require a logged-in user. If not every authenticated user should be able to manage pages, layer in your own authorization:

```php
'admin' => [
    'middleware' => ['web', 'auth', 'can:manage-pages'],
],
```

where `manage-pages` is a `Gate::define()` or policy ability your application defines - Inlay has no opinion on how you express it, it just needs a middleware name Laravel already understands.

You do not need to do anything else to make this middleware apply to every admin action, not just the first page load - `Inlay::adminRoutes()` already handles registering it as [Livewire persistent middleware](https://livewire.laravel.com/docs/security#middleware-persistence-with-hierarchical-routing) for you. See the README's "[Why Livewire middleware persistence matters](../README.md#why-livewire-middleware-persistence-matters)" if you want the mechanical detail. This is a separate setting from `config('inlay.pages.middleware')`, which guards public Pages instead - see [docs/routing.md](routing.md).

## 5. Write your first block

A block is a normal Blade class component that implements `InlayBlock` - a marker interface with no required methods:

```php
// app/Inlay/Blocks/Hero.php
namespace App\Inlay\Blocks;

use Illuminate\View\Component;
use Illuminate\View\View;
use WeArePixel\Inlay\Attributes\BlockMeta;
use WeArePixel\Inlay\Attributes\Text;
use WeArePixel\Inlay\Contracts\InlayBlock;

#[BlockMeta(label: 'Hero', category: 'Headers', description: 'A page heading with introductory copy.')]
final class Hero extends Component implements InlayBlock
{
    public function __construct(
        #[Text(label: 'Heading', maxLength: 80)]
        public string $heading = 'Welcome',

        #[Text(label: 'Subheading')]
        public string $subheading = '',
    ) {}

    public function render(): View
    {
        return view('blocks.hero');
    }
}
```

```blade
{{-- resources/views/blocks/hero.blade.php --}}
<section class="hero">
    <h1>{{ $heading }}</h1>
    @if ($subheading)
        <p>{{ $subheading }}</p>
    @endif
</section>
```

Register it by handle in `config/inlay.php`:

```php
'blocks' => [
    'hero' => \App\Inlay\Blocks\Hero::class,
],
```

Reload `/inlay/pages` and confirm the config picked it up:

```bash
php artisan config:clear   # only needed if config is cached in this environment
```

## 6. Configure the preview

The editor's preview pane is a real iframe pointed at your own frontend. Tell Inlay which of your views renders a composition:

```php
// app/Providers/AppServiceProvider.php
use WeArePixel\Inlay\Facades\Inlay;

public function boot(): void
{
    Inlay::previewView('inlay-previews.default');
}
```

```blade
{{-- resources/views/inlay-previews/default.blade.php --}}
<x-app-layout>
    {{ $composition }}
</x-app-layout>
```

If you skip this step, the preview pane still works - it falls back to a plain, clearly-labeled unstyled wrapper so you always know it isn't your real design yet.

## 7. Create and publish your first page

1. Visit `/inlay/pages` and click **New page**. Give it a title. A public path is optional - leave it blank if you'd rather render this page through a route your own application already owns (step 8, option B below).
2. In the editor, click **+ Add**, choose **Hero** from the picker, and edit its heading in the inspector.
3. Watch the preview pane update.
4. Click **Publish**.

## 8. Render it on your actual site

Pick one of two options - see [docs/routing.md](routing.md) for the full reference.

**Option A - let Inlay serve it publicly.** Give the page a public path (e.g. `about`) in step 7, then register `Inlay::pageRoutes()`:

```php
// routes/web.php
Inlay::pageRoutes(prefix: 'pages'); // -> /pages/about
```

```blade
{{-- resources/views/inlay/pages/show.blade.php --}}
<x-app-layout>
    {{ $composition }}
</x-app-layout>
```

Configure this view path with `config('inlay.pages.view')` if you'd rather use a different name.

**Option B - render it through a route you already own:**

```php
// routes/web.php
use WeArePixel\Inlay\Models\Page;

Route::get('/{page:path}', function (Page $page) {
    return view('pages.show', ['page' => $page]);
});
```

```blade
{{-- resources/views/pages/show.blade.php --}}
<x-app-layout>
    <x-inlay::render :page="$page" />
</x-app-layout>
```

Either way, visit the page's URL and you should see the published `Hero` block, styled by your own application's CSS - not Inlay's.

## Next steps

- [docs/routing.md](routing.md) - the full public routing reference (prefixes, nested paths, the homepage, `Inlay::url()`).
- [docs/blocks.md](blocks.md) - the full block-authoring reference (fluent settings, enums, `LinkValue`, nested Livewire).
- [docs/editor.md](editor.md) - the editor-facing workflow in plain language.
- [README.md](../README.md#current-limitations) - what this release deliberately doesn't include yet.
