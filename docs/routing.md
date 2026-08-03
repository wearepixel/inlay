# Public routing

Inlay never registers public routes automatically. Opt into package-owned Page routing with `Inlay::pageRoutes()`, or render Pages through routes your application already owns.

This document covers the first option in full. For the second, see the README's "[Embedded rendering](../README.md#embedded-rendering)".

## The prefix/path/URL relationship

A Page's `path` column is a bare, application-relative value - `about`, `about/team`, `test/test` - never a full URL, a hostname, a leading slash, or the route prefix. The prefix is configuration, layered on only when routing a request or generating a URL:

```php
Inlay::pageRoutes(prefix: 'pages');
```

A Page stored with `path` = `test/test` resolves at `/pages/test/test`. **The configured prefix is not part of the Page's stored path. It is added only when routing or generating the public URL.** Changing the prefix changes every Page's URL immediately - nothing in the database changes:

```php
Inlay::pageRoutes(prefix: 'content'); // the same Page above is now at /content/test/test
```

## Root vs. prefixed routing

```php
Inlay::pageRoutes();                 // root: / -> homepage, /about -> path "about"
Inlay::pageRoutes(prefix: 'pages');  // prefixed: /pages -> homepage, /pages/about -> path "about"
```

`'pages'`, `'/pages'`, `'pages/'`, and `'/pages/'` all normalise to the same internal prefix - use whichever reads naturally; the documented form is the bare `'pages'`.

**Root-level (unprefixed) routing registers a genuine catch-all.** It must be registered _last_, after your application's own routes - a route your application registers first always wins for a given URL, since Inlay cannot detect every possible runtime conflict at registration time. A prefixed registration (`prefix: 'pages'`) is safer and doesn't need to be registered last, since it only matches URLs under that one segment.

```php
// routes/web.php
Route::get('/blog/{slug}', BlogController::class); // your own routes first
// ...
Inlay::pageRoutes(); // registered last - a real catch-all otherwise
```

Inlay does not, and will not, modify `routes/web.php` for you.

## Nested paths

A path may contain slashes - `about/team` resolves at `/pages/about/team` (or `/about/team` at the root). `Inlay::url()` and the registered route both handle this natively; no special configuration is needed.

## The homepage

Exactly one Page may be the homepage at a time, set via **Page Settings** in the editor (or `is_homepage` on the model). The homepage resolves at the route group's own root - `/` unprefixed, or `/pages` and `/pages/` (both, identically) under a `prefix: 'pages'` registration. There is no magic empty-string path that means "this is the homepage" - a Page's `path` and its homepage status are independent; a homepage Page may also have `path === null`.

Setting a new homepage replaces the previous one automatically. Deleting the homepage Page needs no separate cleanup - the flag is a column on the Page row itself, so it's gone the moment the row is. A draft homepage returns a normal 404 publicly, exactly like any other draft Page.

## What resolves publicly, and what doesn't

Only **published** Pages resolve through `Inlay::pageRoutes()`. A draft Page, or a Page whose `path` is `null` and which is not the homepage, returns a normal 404 - indistinguishable from "no route matched" to a visitor. A Page with no public path is intentionally host-rendered only; see the README's "[Embedded rendering](../README.md#embedded-rendering)" for that path instead.

## The public Page view

```php
Inlay::pageRoutes(view: 'inlay.pages.show'); // the default; override via the view argument or config('inlay.pages.view')
```

The configured view receives exactly `$page`, `$composition` (an `Illuminate\Contracts\Support\Htmlable`), and `$context` (currently always `null` for package-owned routing - a route your application owns and calls `Inlay::renderPage(context: [...])` from is how you supply real request-specific context). Your view owns layout, CSS, JS, SEO, and every other concern entirely - Inlay does not inspect or modify any of it. If the configured view doesn't exist, Inlay throws a clear exception naming the expected view, rather than silently falling back to the admin's preview wrapper - that fallback is for the admin preview only, never for a real public request.

## Metadata

```blade
<title>{{ $page->effectiveMetaTitle() }}</title>
@if ($page->effectiveMetaDescription())
    <meta name="description" content="{{ $page->effectiveMetaDescription() }}">
@endif
```

`effectiveMetaTitle()` returns the Page's explicit meta title, falling back to its title. `effectiveMetaDescription()` returns the explicit meta description, or `null` if none is set - nothing is auto-generated from block content. Inlay never injects these tags into your layout itself; your view is responsible for echoing them wherever your own `<head>` expects them.

Title, path, and meta fields are revisioned exactly like block settings: publishing snapshots them, and editing them in the admin never changes what's already public until you publish again. `path` and `is_homepage` are deliberately **not** revisioned - they're live routing/identity state a public request has to resolve against right now, not content a past revision should remember having had. A path change (or a homepage change) takes effect immediately, without needing a publish.

## `Inlay::url()`

```php
Inlay::url($page); // '/pages/about', '/pages' (homepage), or null (no public path, not the homepage)
```

Generates a Page's public URL from live configuration, never from the database - a Page row never stores a full URL, a hostname, or a prefix. When `pageRoutes()` has been registered in the current process, it generates through Laravel's own named route (`inlay.pages.show`), which correctly handles nested paths. When it hasn't (a queue worker, a console command, or any process that never called `pageRoutes()` itself), it falls back to building the URL from `config('inlay.pages.prefix')` directly, so URL display inside the admin stays useful regardless of which part of your application registers routes.

Calling `pageRoutes()` twice in one process with equivalent configuration is a harmless no-op. Calling it twice with genuinely different prefix/view/middleware throws - only one public Page route registration can be "the" active one for a given process.

## Route names

Public routes register under `inlay.pages.*` (currently just `inlay.pages.show`). Admin routes register under `inlay.admin.*`.
