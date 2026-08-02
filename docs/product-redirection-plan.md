# Product Redirection Plan — from page-builder engine to a visual composition layer for Laravel applications

Date: 2026-08-02
Status: Decision-ready — no implementation started
Supersedes nothing: `docs/superpowers/specs/2026-07-10-blade-page-builder-design.md` remains the historical record of why the current foundation exists and is not being deleted or rewritten. This document has already been revised once, to reconcile it against the corrected product vision in `docs/product-vision.md` — see the **Reconciliation appendix** near the end for exactly what changed and why.

## Why this document exists

The current repository is a working, tested, schema-driven page-building **engine**. It is not yet the **product**: an open-source, Laravel-native visual composition layer that a developer installs into an existing custom application — not a CMS the application is rebuilt on top of — so that non-developers can safely compose and configure developer-built frontend experiences, both public and application-aware, without the developer building an admin shell, routing, or publishing controls themselves.

This document inspects the actual code that exists today, classifies what should survive unchanged, evolve, or be replaced, and lays out a concrete, staged path to the product described in `docs/product-vision.md`. It does not begin implementation. Every recommendation is grounded in specific files and classes in this repository, not generic CMS wisdom.

---

## 1. Current foundation

| Part                                                                                                | File(s)                                          | Classification                                    | Why                                                                                                                                                                                                                                                                                                                                                                                                                          |
| --------------------------------------------------------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Page`, `PageSection`, `PageRevision` models                                                        | `src/Models/*.php`                               | **Evolve**                                        | The shape (final classes, `array` casts on JSON columns, an enum cast for status) is correct and proven by 32 passing tests. They need new columns (site scope, hierarchy, an optional public path, a Page Definition handle) and new relationships, not a rewrite.                                                                                                                                                          |
| `pages`, `page_sections`, `page_revisions` tables                                                   | `database/migrations/2025_01_01_*.php`           | **Evolve**                                        | The three-table split (live draft / append-only publish log) is the right shape for the fuller product too — see §4. They gain columns and siblings (sites, settings, navigation, media), they are not replaced.                                                                                                                                                                                                             |
| `PageStatus` enum                                                                                   | `src/Enums/PageStatus.php`                       | **Retain**                                        | `Draft`/`Published` is sufficient for the target product's v1. No change proposed.                                                                                                                                                                                                                                                                                                                                           |
| `Schema` / `Field` fluent builder                                                                   | `src/Schema/Schema.php`, `src/Schema/Field.php`  | **Evolve**                                        | The builder pattern (`Schema::make()->text()->select()->mapsToClass()`) and the readonly `Field` DTO are exactly the right developer experience and should not change shape. What changes underneath is what `Field::$type` points at — see the next row.                                                                                                                                                                    |
| `FieldType` enum                                                                                    | `src/Schema/FieldType.php`                       | **Replace**                                       | A closed, backed enum with 8 hardcoded cases is precisely what §18 (Extensibility) says must not remain: a host application cannot register a 9th field type without patching this package. It becomes a registry of `FieldTypeDefinition` implementations — see §18.                                                                                                                                                        |
| `Sectionable` contract                                                                              | `src/Contracts/Sectionable.php`                  | **Evolve, rename**                                | Renamed to `Composable` (§8/§9) to match the corrected vocabulary — "everything rendered is a Component" — but the one required method (`static function schema(): Schema`) and its behavior are unchanged. The previously proposed separate `Moduleable extends Sectionable` is dropped entirely: application-aware behavior is not a registration-time distinction at all (§8).                                            |
| `SectionRegistry`                                                                                   | `src/Support/SectionRegistry.php`                | **Evolve**                                        | `resolve()`/`slugs()` and the config-array-backed singleton pattern are correct and already regression-tested (`tests/Unit/SectionRegistryTest.php`). It becomes the internals of a single component registry reachable through a `PageBuilder` facade, but its public method names and behavior (throw `InvalidArgumentException` on an unknown handle) are preserved, not deleted.                                         |
| `SectionRenderer`                                                                                   | `src/Support/SectionRenderer.php`                | **Evolve**                                        | The resolve → instantiate → `render()->with($data)->render()` → catch-and-log-per-component logic is correct and is the single best piece of defensive design in the codebase. It is extracted behind a `RendererDriver` interface (§11) as `BladeRendererDriver`, verbatim, so this exact behavior is preserved rather than reimplemented.                                                                                  |
| `PublishPage`, `RestorePageRevision` actions                                                        | `src/Actions/*.php`                              | **Retain**                                        | Transactional, single-purpose, non-destructive restore (`RestorePageRevision` always calls `PublishPage` again rather than rewinding history). This is correct and stays. Only additions are event dispatch (§19) around the existing `execute()` calls.                                                                                                                                                                     |
| Draft/publish/revision model                                                                        | (behavior, not one file)                         | **Retain**                                        | `page_sections` as the one live draft tree, `page_revisions` as an append-only whole-tree snapshot log, is the right foundation for the fuller product and is not being redesigned.                                                                                                                                                                                                                                          |
| `PageEditor` Livewire component                                                                     | `src/Livewire/PageEditor.php`                    | **Replace (as a monolith), retain (as behavior)** | At ~300 lines it already owns component CRUD, drag-order persistence, prop filtering/validation, image upload, publish, and restore — six responsibilities in one class. §7 decomposes it into an orchestrator plus five focused child components. Every operation it currently performs is preserved; only its shape as a single component changes.                                                                         |
| `filterPropsAgainstSchema()` / `filterLinkValue()` / `filterScalarValue()` / `isAllowedUrlScheme()` | `src/Livewire/PageEditor.php`                    | **Retain, relocate**                              | This is the package's real security boundary (regression-tested in `tests/Feature/PropTamperingRegressionTest.php`) and must not be diluted while `FieldType` becomes a registry. Its logic moves onto `FieldTypeDefinition::validate()` per type (§18) so each field type owns its own coercion rule, but the net behavior — undeclared keys dropped, values coerced to type, Link URLs allowlisted — is preserved exactly. |
| Field partials (`resources/views/fields/*.blade.php`)                                               | 8 files                                          | **Evolve**                                        | The one-partial-per-type, resolved-by-convention pattern is correct and becomes the seed of the field-type registry's `view()` contract (§18) — a custom field type registers its own partial the same way.                                                                                                                                                                                                                  |
| `page-editor.blade.php` fixed 3-column layout                                                       | `resources/views/livewire/page-editor.blade.php` | **Replace**                                       | Superseded by the package-owned admin screens in §6/§7. The _editing operations_ it wires up (drag via SortableJS, per-field `wire:model`) are retained; the specific 3-column Blade view is not.                                                                                                                                                                                                                            |
| CDN-loaded SortableJS                                                                               | inline `<script>` in the view above              | **Replace**                                       | See §16 — this is a supply-chain and offline-reliability liability that must not ship in the target product.                                                                                                                                                                                                                                                                                                                 |
| `config/blade-page-builder.php`                                                                     | `config/blade-page-builder.php`                  | **Evolve**                                        | The `sections` map and `disk` key are retained as concepts and grow into the fuller config surface in §12.                                                                                                                                                                                                                                                                                                                   |
| `BladePageBuilderServiceProvider`                                                                   | `src/BladePageBuilderServiceProvider.php`        | **Evolve**                                        | Config merge, migration loading, view loading, Blade component registration, and Livewire namespace registration are all correct and stay. It gains conditional admin route registration, asset publishing, and command registration.                                                                                                                                                                                        |
| Test suite (32 tests, 108 assertions)                                                               | `tests/**`                                       | **Retain as the regression baseline**             | Nothing here is discarded. Some tests (`PageEditorLayoutTest`, `PageEditorMountPointTest`, `PageEditorHistoryTest`) will need to move or be adapted as the editor is decomposed and reached through a package route instead of a bare component tag (§20, Stage F), but their _assertions about behavior_ — history restore works, the mount tag renders — are what must keep passing, not their literal current form.       |

**Naming note, updated by the vision correction:** the product's public name is now explicitly unsettled — `docs/product-vision.md`'s naming section shortlists Fixture, Lattice, and Inlay against the current `wearepixel/blade-page-builder` / `WearePixel\BladePageBuilder` working name, without committing to any of them pending trademark, Composer-namespace, and domain checks done separately. **This plan no longer recommends renaming the Composer package or namespace as part of Milestone 0** — that decision is deferred until the product name is settled (this reverses a recommendation made in the previous draft of this plan; see the reconciliation appendix). It still recommends renaming the internal `Sectionable` contract to `Composable` now, since that is a vocabulary correction inside the codebase — matching "everything is a Component" — independent of whatever the product is eventually called publicly. The `PageBuilder` facade name used throughout this plan is a neutral placeholder for "the package's static entry point," not a commitment to that being the product's final public name.

---

## 2. Target product experience

The bar: a competent Laravel developer, starting from a normal Laravel app, has a working visual composition layer in about ten minutes.

### Install

```bash
composer require wearepixel/page-builder
php artisan page-builder:install
php artisan migrate
```

`page-builder:install` (detailed fully in §15) publishes `config/page-builder.php` and the package's compiled admin assets, asks a small number of interactive questions (admin path prefix, which middleware group to protect it with, upload disk), creates the package's own database rows that are safe to create automatically (an initial `Site`), and prints exact next-step instructions. It does not silently edit any file the host application already owns.

### Wire up routing

In `routes/web.php`, after the host's own routes:

```php
use WearePixel\PageBuilder\Facades\PageBuilder;

PageBuilder::routes();
```

This registers both the admin route group and the public catch-all (§5). A host that wants only one half calls `PageBuilder::adminRoutes()` or `PageBuilder::publicRoutes()` instead — and a host composing only application-embedded surfaces (§4, §5) may call neither, relying entirely on `PageBuilder::renderPage()` from its own existing routes.

### Visit the admin

```
http://app.test/admin/pages
```

(prefix configurable at install time). Protected by whatever middleware was chosen during install — the package assumes nothing about the host's auth package and ships a permissive default (§17) so this works immediately even before the host has configured anything further.

### Create the first page

In the admin: **New Page** → title "Home" → toggle "Set as homepage" → **Create**. Lands in the page editor with an empty component list.

### Generate the first custom component

```bash
php artisan make:page-component HeroSplit
```

Generates `app/PageBuilder/Components/HeroSplit.php` (a stub `Illuminate\View\Component` implementing `Composable`, with a starter `schema()`) and `resources/views/page-builder/components/hero-split.blade.php`.

### Register (or let it be discovered)

If it lives in the default scanned directory (`app/PageBuilder/Components`), nothing further is required — component discovery (§10) picks it up. To register something outside that convention (a vendor package's own components, a non-default namespace), in `AppServiceProvider::boot()`:

```php
use WearePixel\PageBuilder\Facades\PageBuilder;
use App\PageBuilder\Components\HeroSplit;

PageBuilder::component(HeroSplit::class);
```

### Compose and publish

Back in the admin editor: **Add component** → "Hero Split" → edit its fields with the live preview updating alongside → **Publish**.

### View it live

```
http://app.test/            (resolved as the configured homepage)
http://app.test/about/team  (a nested page, if hierarchy was used)
```

Rendered by the package's public catch-all route using the exact same renderer the admin preview used — no separate production renderer to diverge from the preview (§11, carried forward unchanged from the current `RenderPage` design).

**The same flow applies beyond a marketing homepage.** A `CollectionPage` composed from a `CampaignHero` and a real, data-backed `ProductCollection` component follows an identical install → register → compose → publish path. The only addition, covered in §4 and §9, is a **Page Definition** — a developer-authored class constraining what a collection page is allowed, required, and forbidden to contain — chosen once, at creation, so a merchandiser composing that page can rearrange freely without being able to remove the product listing the page exists to show.

---

## 3. Product boundaries

**The package owns:** admin interface, page CRUD, page metadata, page hierarchy, public page resolution (for pages that have one), publishing, revisions, preview, CMS-level settings, navigation, component and Page Definition registration, package assets, installation and scaffolding commands.

**The host application owns:** application-specific business models, the visual implementation and data resolution of every component it registers, its own auth/middleware decisions, application-specific business data and logic, deployment and infrastructure.

**This is not a CRUD-administration boundary, and that distinction needs to be explicit, not implied.** The package never gives an editor arbitrary access to the host's Eloquent models the way Filament or Nova would give a developer's internal team. It gives an editor access to exactly the components and fields a developer chose to expose, arranged inside exactly the constraints a developer defined. If a project needs an internal team to do arbitrary data administration, that remains Filament's or Nova's job entirely — this package's presence in an application doesn't change that boundary, and the two are expected to coexist rather than compete.

Three places in the "package owns / host owns" split need pushing back on rather than accepting as given:

- **"Application-specific data" is not cleanly host-only.** A component that resolves live application data (a `ProductGrid`, a `BookingAvailability` panel) is, by definition, a package-registered component that reaches into host data. The honest boundary is: the package owns the _registration and rendering contract_ every component fulfils — one interface, one rendering pipeline (§8) — while the host owns the _data-fetching code inside that component's own class_, and the package never copies that resolved data into its own storage (§4, §11). Neither side owns "application data" outright; the package owns the contract, the host owns the data and the code that fetches it.
- **"Custom middleware and authentication decisions" is not cleanly host-only either.** The package must own _how_ configured middleware gets attached to its own route group (package plumbing — see §5), even though _which_ middleware/guard is chosen is entirely the host's call.
- **Settings needs a narrower boundary than "the package owns settings."** The package should own a small, fixed, typed set of CMS-relevant settings (site name, homepage, default meta, logo/favicon/social image, robots behaviour, head/body scripts — see §12), not a general-purpose host application settings store.

Navigation deserves one clarification, not a correction: the package owns navigation _structure, storage, and CRUD_, but does **not** own navigation _visual rendering_ by default. It exposes a resolved tree; the host's own Blade layout decides how to draw it (§13).

---

## 4. Domain model

### Sites — explicit recommendation

**Introduce a `sites` table now**, even though the first usable release exposes exactly one site and no multi-site UI. Reasoning unchanged from the previous draft of this plan: `pages` currently has no tenant/site scope at all, and retrofitting one after real installations exist is a breaking migration for every adopter, while adding it now costs nothing beyond one join and one auto-created default row.

### Proposed tables

| Table                     | Purpose                                  | Significant columns                                                                                                                                                                                                                                                                      | Relationships                                                                                                 | v1 or deferred                                                                                                                                                  |
| ------------------------- | ---------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `sites`                   | Tenant/site scope seam                   | `id`, `name`, `domain` (nullable), `is_default` (bool)                                                                                                                                                                                                                                   | `hasMany` pages, navigations, one `site_settings` row                                                         | **Required for v1** (schema only — no multi-site UI)                                                                                                            |
| `pages` (evolve)          | Canonical composition + editorial record | adds `site_id` FK, `parent_id` FK nullable (self-referential), **`path` (nullable** — see routing note below), `definition` (nullable string handle — the registered Page Definition governing this page, §8/§9) to the existing `title`/`slug`/`status`/`meta_title`/`meta_description` | `belongsTo` site, `belongsTo` parent (self), `hasMany` children (self), existing `hasMany` sections/revisions | **Required for v1**                                                                                                                                             |
| `page_sections` (evolve)  | Live draft component tree                | unchanged shape; no schema-level distinction between kinds of registered component — capability is a code-level property of the component class (§8), never a database one                                                                                                               | unchanged                                                                                                     | **Retained as-is**                                                                                                                                              |
| `page_revisions` (evolve) | Append-only publish log                  | unchanged; add a DB index on `(page_id, created_at)` if not already implied by the FK                                                                                                                                                                                                    | unchanged                                                                                                     | **Retained as-is**, pagination bug fixed in application code (§7), no schema change                                                                             |
| `site_settings`           | Typed, per-site CMS settings             | `site_id` FK (unique), `name`, `homepage_page_id` (FK, nullable), `default_meta_title`, `default_meta_description`, `logo_media_id`/`favicon_media_id`/`default_social_image_media_id` (FK to `media`, nullable), `robots_index` (bool), `head_scripts`/`body_scripts` (nullable text)   | `belongsTo` site, `belongsTo` homepage page, `belongsTo` media (×3)                                           | Minimal columns (`name`, `homepage_page_id`, default meta text) **required for v1**; the three media-backed columns deferred to Milestone 2 once `media` exists |
| `media`                   | Minimal asset record                     | `id`, `disk`, `path`, `filename`, `mime_type`, `size`, `alt_text` (nullable), `uploaded_by` (nullable int, no FK — same pattern as today's `page_revisions.created_by`)                                                                                                                  | none beyond FKs pointing at it from settings/section props                                                    | **Required for v1** (minimal — see §14)                                                                                                                         |
| `navigations`             | Named navigation trees                   | `id`, `site_id` FK, `handle` (e.g. `main`, `footer`, `legal`), `name`                                                                                                                                                                                                                    | `belongsTo` site, `hasMany` navigation_items                                                                  | **Deferred to Milestone 2**                                                                                                                                     |
| `navigation_items`        | Nested nav entries                       | `id`, `navigation_id` FK, `parent_id` FK nullable (self-referential), `page_id` FK nullable (`nullOnDelete`), `url` (nullable — exactly one of `page_id`/`url` set), `label`, `target`, `order`, `visible` (bool)                                                                        | `belongsTo` navigation, `belongsTo` parent (self), `belongsTo` page (nullable)                                | **Deferred to Milestone 2**                                                                                                                                     |

**One relaxation from the previous draft: `pages.path` (and public catch-all routing participation generally) is now optional, not assumed.** A `Page` is fundamentally a composition-plus-editorial record; having its own public, slugged URL is one thing a page can do, not a defining property of what a page is. A page created purely to be embedded inside an existing ecommerce collection route, or inside an authenticated account dashboard, can have a null `path` and simply never appears in, or is matched by, the public catch-all (§5) — it is rendered only where a host explicitly calls `PageBuilder::renderPage($page)` from a route or controller it already owns. This is the concrete mechanism that keeps the architecture from assuming every composition is a standalone public marketing page with its own slug, without introducing a new composition-root concept the first release doesn't need.

If a genuinely distinct composition root (not a page at all — a configurable region embedded inside an existing authenticated screen, say) becomes a real, demonstrated need later, the cleanest path is likely separating "the ordered component tree plus its revisions" from "the page-specific routing and SEO wrapper around it," so a future non-page surface could reuse the same composition machinery without duplicating it. That is a long-term seam worth keeping in mind, not a v1 table, and no code or schema decision here should make it harder later than it needs to be.

### Explicitly assessed and rejected (for now)

- **Page Definitions as database rows** — rejected. A Page Definition constrains _behaviour_ (permitted, required, locked, and default components) and is a code/policy concern, not editorial content — it renders nothing and is not a Component (see `docs/product-vision.md`'s "fundamental building block" section for why treating it as a fifth kind of Component would be a false equivalence). It is a plain PHP class (`PageDefinition`, §8/§9) referenced by `pages.definition` as a string handle. No `page_definitions` table. **Unlike the previous draft of this plan, Page Definitions are not deferred to Milestone 2/3** — see §21: the ecommerce and application-aware use cases this product now explicitly targets need placement guardrails from the first usable release, not as a later refinement.
- **A separate `Layoutable` contract, a `pages.layout` column, and a dedicated "layout" concept generally** — rejected for v1, more fully than the previous draft proposed. Wrapping page content is a capability a Component may declare (`WrapsContent`, §8), not a fourth registered concept. But v1 doesn't need that capability exercised at all: the composed output is rendered inside whatever Blade layout the host application's route or controller already extends — exactly how the current `RenderPage` component is already meant to be dropped into a host-owned view today. No `pages.layout` column, no bundled default wrapper, nothing new. `WrapsContent` stays in the Component model for the case where an editor should choose between multiple registered page shells — a real but genuinely Milestone 2/3 need, and one that may turn out to be unnecessary if most host applications are content choosing their own single shell in code, which is the common case.
- **Global sections / reusable patterns** — deferred, not rejected outright. Revisit in Milestone 3+ only if real usage demands it. Listed again under "what not to build yet."
- **A generic `settings` key/value (EAV) table** — rejected in favour of the structured `site_settings` table above.
- **Full digital-asset-manager tables** (folders, collections, tags, transformation variants) — rejected for v1. See §14.

---

## 5. Routing architecture

### Admin routes

Registered by the package inside its own route group:

- Prefix: `config('page-builder.admin.prefix', 'admin/pages')`.
- Middleware: `config('page-builder.admin.middleware', ['web'])` — the install command (§15) nudges toward adding `auth`, but the package assumes nothing about which guard.
- Route names: `page-builder.pages.index`, `page-builder.pages.create`, `page-builder.pages.edit`, `page-builder.settings`, `page-builder.navigation`, etc.

### Public routing

A single catch-all, registered **last**, so any route the host has already defined always wins:

```php
Route::get('{path?}', PublicPageController::class)
    ->where('path', '.*')
    ->name('page-builder.public.show');
```

Resolution is a single indexed lookup against the denormalized `pages.path` column — `Page::where('site_id', $site->id)->where('path', $path)->firstOrFail()` — not a recursive walk up `parent_id` per request. The empty path (`/`) resolves via `site_settings.homepage_page_id`. **Only pages with a non-null `path` are matched here** (§4) — a page created purely to be embedded inside a host-owned route has no `path` and is simply invisible to this lookup, by design, not by exception-handling.

### The `PageBuilder::routes()` API

```php
PageBuilder::routes();          // registers both halves — the common case
PageBuilder::adminRoutes();     // admin only
PageBuilder::publicRoutes();    // public catch-all only
```

The service provider does **not** auto-register any routes on boot. Routes only exist if the host explicitly calls one of these. This is what makes "disabling package public routes" trivial, and what makes route-conflict risk manageable: the host controls exactly where in its own route file the catch-all is registered relative to everything else.

### Custom rendering without the package's controller

For a host that wants its own controller to own the response, or that wants to render a composition inside a route it already controls (an ecommerce collection page, an authenticated account screen):

```php
PageBuilder::renderPage(Page $page, bool $draft = false, array $context = []): Htmlable
```

This is the same primitive the public controller and the admin preview both call internally (§11). `$context` is how a host supplies data a component can't resolve on its own — most commonly, the real domain object the surrounding route is already scoped to. A component that declares it needs a given context key (§8/§9's `RequiresContext`) receives it merged alongside its own stored, editorial props; if a required key is missing, the same catch-and-skip discipline `SectionRenderer` already applies to a malformed component (§11) applies here too — never a fatal error for a visitor, and a clear, specific warning for an editor.

This is also the concrete mechanism behind "route-backed ecommerce pages" and "sections of existing routes": a host's own `CollectionController@show(Collection $collection)` looks up its own `page_id` column — something the host adds to its own `collections` table, not something this package needs to know about — and calls `PageBuilder::renderPage($page, context: ['collection' => $collection])`. No polymorphic binding table, no new mechanism the package has to own.

### Preview routes

- **In-admin preview**: no separate route needed, lives inside the already-authenticated admin middleware group.
- **Full-page/new-tab preview**: `page-builder.pages.preview` inside the admin group.
- **Signed external preview**: a `URL::signedRoute()`-generated, time-limited link outside the admin middleware group, calling the same `renderPage($page, draft: true)` primitive.

### Route conflicts, custom domains, multi-site

Site resolution for a request is delegated to a single swappable `SiteResolver` interface. The only implementation shipped now trivially returns the one default site. Custom domains and true multi-site routing (explicitly **not** being built now) become "write a different `SiteResolver` implementation" later, rather than a routing rearchitecture.

---

## 6. Complete administration application

| Screen                | Purpose                                                                                           | Primary actions                                                                            | Required data                                                   | v1?                                                                    |
| --------------------- | ------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ | --------------------------------------------------------------- | ---------------------------------------------------------------------- |
| Dashboard             | Orientation / landing                                                                             | Create page, jump to recent                                                                | Recent pages, counts                                            | **Minimal version in v1** — an activity/attention feed is Milestone 2  |
| Page index            | Browse and manage all pages                                                                       | Create, edit, duplicate, delete, publish/unpublish, filter by status                       | Paginated pages with status, `updated_at`, parent               | **v1 — core**                                                          |
| Page creation         | Start a new page                                                                                  | Title (auto-slug), pick parent, **choose a Page Definition if one applies**, save as draft | Existing pages (for parent picker), registered Page Definitions | **v1 — the Page Definition picker ships in v1**, not deferred (§21)    |
| Page editor           | Compose and edit components                                                                       | See §7 in full                                                                             | Page, components, registered components/definitions             | **v1 — core**                                                          |
| Page settings         | Slug, meta, hierarchy, homepage toggle, **optional public path** (blank for a host-embedded page) | Edit and save                                                                              | Page record, site settings                                      | **v1**                                                                 |
| SEO & social metadata | Search/social presentation                                                                        | Edit title/description/social image                                                        | Page record, media (for image)                                  | Text fields **v1**; social image field **Milestone 2** (needs `media`) |
| Navigation manager    | Build/reorder nav trees                                                                           | Add item, nest, reorder, link to page or URL                                               | `navigations`/`navigation_items`                                | **Milestone 2**                                                        |
| General settings      | Site-wide defaults                                                                                | Edit name, homepage, default meta, logo/favicon                                            | `site_settings`                                                 | Minimal fields **v1**; logo/favicon **Milestone 2**                    |
| Revision history      | Audit and restore                                                                                 | List, restore                                                                              | Paginated `page_revisions`                                      | **v1**                                                                 |
| Preview               | See the draft as a visitor would                                                                  | Toggle device width, open full-page, copy signed link                                      | Rendered HTML via `renderPage()`                                | Inline + full-page **v1**; signed external link **Milestone 2**        |
| Media/asset picker    | Choose or upload an image                                                                         | Upload, search, select                                                                     | `media` table                                                   | **v1 — minimal grid, not a DAM**                                       |

Design direction unchanged: the calm, structured, low-noise interaction quality of Statamic is the right reference point, without borrowing its visual identity — achievable only if the admin ships its own compiled CSS/JS (§16).

---

## 7. Page editor redesign

The current `PageEditor` (`src/Livewire/PageEditor.php`) already blends six responsibilities: component CRUD, drag-order persistence, prop filtering/validation, image upload, publish, and restore. Decompose now, before adding hierarchy, Page Definition guardrails, SEO, a categorized component picker, duplicate/delete, responsive/full-page preview, an unpublished-change indicator, validation errors, and broken-component warnings — not after.

**Proposed shape** — one thin orchestrator plus five focused children, communicating via Livewire's own `$this->dispatch()` rather than deep prop drilling:

- **`PageEditor`** (orchestrator) — loads the `Page`, holds which component is selected, renders the children below, owns top-level actions: `publish()`, preview-mode toggle.
- **`ComponentList`** — everything the current component's left column does today (`updateOrder`, `addSection`, `selectSection`) plus new actions: `removeComponent`, `duplicateComponent`. The "Add component" picker evolves from a flat button list into a searchable, category-grouped picker, filtered by the current page's Page Definition when one applies (§8, §9).
- **`ComponentFieldPanel`** — renders the schema-driven form for the selected component. Persistence delegates to a `PersistComponentProps` action wrapping the existing (relocated, see §18) per-field-type validation logic. This is also where per-field validation errors become visible to the editor for the first time.
- **`PreviewPane`** — presentational only; receives already-rendered HTML or a preview URL. Adds a responsive-width toggle and a link to the full-page preview route (§5).
- **`RevisionHistory`** — extracted from today's `@if ($showingHistory)` block; gains real pagination.
- **`PageSettingsPanel`** — entirely new: title, slug, status, parent, Page Definition (chosen at creation, see below), SEO fields.

Supporting non-Livewire pieces: `PersistComponentProps` (action wrapping the schema-driven filter/validate logic, now sourced from `FieldTypeDefinition::validate()` per §18), `MediaUploader` (service, §14), and the existing `PublishPage`/`RestorePageRevision` actions, unchanged.

**A Page Definition, when one applies, is chosen once, at page creation** — not as a casual settings toggle changeable at any time. Changing it afterward is possible but deliberately validated (an existing component no longer allowed under the new definition, or a newly required component now missing, is surfaced exactly like any other constraint violation) rather than silently applied, since a change here can otherwise quietly break the guarantee a Page Definition exists to provide.

Feature-to-owner mapping:

| Requirement                                                 | Lives in                                                                                                                                                                                                                |
| ----------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Title, slug, status, hierarchy, Page Definition selection   | `PageSettingsPanel` (new)                                                                                                                                                                                               |
| SEO                                                         | `PageSettingsPanel` (new, a tab)                                                                                                                                                                                        |
| Component library / categories, filtered by Page Definition | `ComponentList`'s picker (evolved)                                                                                                                                                                                      |
| Drag-and-drop                                               | `ComponentList` (retained behavior)                                                                                                                                                                                     |
| Delete / duplicate components                               | `ComponentList` (new) — disabled for a component the current Page Definition locks                                                                                                                                      |
| Required-component guardrail                                | `ComponentList` — a required component that's missing is surfaced the same way a broken-component warning is (§11)                                                                                                      |
| Responsive preview                                          | `PreviewPane` (new)                                                                                                                                                                                                     |
| Full-page preview                                           | `PreviewPane` + route from §5                                                                                                                                                                                           |
| Unpublished-change indicator                                | `PageEditor` orchestrator — a cheap comparison (timestamp or hash) between `page_sections` state and the latest `page_revisions.snapshot`                                                                               |
| Autosave                                                    | Already exists in effect — this slice makes it _visible_ with a "Saved" indicator                                                                                                                                       |
| Explicit publishing                                         | `PageEditor` orchestrator (retained)                                                                                                                                                                                    |
| Revisions                                                   | `RevisionHistory` (extracted)                                                                                                                                                                                           |
| Validation errors                                           | `ComponentFieldPanel` (new)                                                                                                                                                                                             |
| Broken-component / missing-context warnings                 | `ComponentList`/`ComponentFieldPanel` — today's silent catch-and-log (§11) needs to surface which components failed, and why (a render exception vs. a missing required context key), when called from an admin context |

---

## 8. The Component model and Page Definitions

**Everything rendered by the builder is one concept: a Component.** One base contract, one registration verb, one rendering pipeline. What the previous draft of this plan called Sections, Modules, and Layouts are the same thing described by different, honest, optional capabilities — not four registration systems mirroring traditional CMS terminology.

### The base contract

```php
interface Composable
{
    public static function schema(): Schema;
}
```

Unchanged in spirit from today's `Sectionable` — renamed to match the corrected vocabulary. This is the entire required surface. A component that implements nothing more than this behaves exactly like today's Sections do.

### Capabilities, not kinds

A component may additionally declare capabilities that change how it's placed or rendered — never a different registration call, never a different interface hierarchy that forks the rendering pipeline:

- **Placed inside page content** — the default; no declaration needed. Most components: a `Hero`, a `ProductGrid`, a `PromoBanner`.
- **Wraps page content** (`WrapsContent`) — a component that renders the rest of the composition as its own slot rather than sitting alongside it. A real behavioral difference (it needs somewhere to put "everything else"), expressed as one capability interface, not a separate `Layoutable` contract. Not exercised in v1 at all — see §4's rationale.
- **Accepts child components** (`AcceptsChildren`) — a component that composes other components inside itself (a two-column region, a tabbed group), rendered recursively through the same renderer. A genuinely new capability worth naming honestly rather than pretending it doesn't exist; the _architecture_ should support it from Milestone 0, even if the editor doesn't offer nested drag-and-drop for it until later.
- **Requires application context** (`RequiresContext`) — a component that needs something the surrounding request supplies beyond its own stored configuration: the collection currently being viewed, the authenticated customer, the booking being managed. This is what the previous draft informally called "a Module" — the honest distinction was never about registration, it was about whether a component needs context handed to it or resolves everything itself. Declared as `public static function requiredContext(): array` returning the context keys it expects (§5, §11).

```php
interface WrapsContent { /* renders $slot alongside its own markup */ }
interface AcceptsChildren { /* renders nested Components recursively */ }
interface RequiresContext { public static function requiredContext(): array; }
```

**What is deliberately not a Component capability:** whether a component can appear only once, whether it can be removed, and whether it can be reordered are not properties of the component's own class. A `ProductGrid` doesn't inherently know it's mandatory on a collection page and freely optional on a marketing page — that's a fact about the _page_, not the component. Baking placement policy into the component itself would conflate "what this component is capable of" with "what a specific page is allowed to do with it." That distinction belongs to the Page Definition below.

### Page Definitions

A **Page Definition** is a developer-authored, non-rendering class that declares what a given kind of page is allowed, required, and forbidden to contain. It is not a Component — it renders nothing, and treating it as a fifth Component variant would be exactly the false equivalence this model is designed to avoid.

```php
abstract class PageDefinition
{
    /** @return array<class-string<Composable>>|null null = unrestricted (all registered components allowed) */
    public function allowedComponents(): ?array
    {
        return null;
    }

    /** @return array<class-string<Composable>> must exist somewhere on a page using this definition */
    public function requiredComponents(): array
    {
        return [];
    }

    /** @return array<class-string<Composable>> present components that cannot be removed or reordered */
    public function lockedComponents(): array
    {
        return [];
    }

    /** @return array<class-string<Composable>> the starting arrangement for a new page using this definition */
    public function defaultComponents(): array
    {
        return [];
    }
}
```

```php
final class CollectionPage extends PageDefinition
{
    public function allowedComponents(): array
    {
        return [CampaignHero::class, ProductCollection::class, EditorialContent::class];
    }

    public function requiredComponents(): array
    {
        return [ProductCollection::class];
    }

    public function lockedComponents(): array
    {
        return [ProductCollection::class];
    }
}
```

An abstract base with sensible no-op defaults (rather than a bare interface every implementer must fully satisfy) keeps this consistent with the codebase's existing preference for minimal ceremony — a Page Definition that only needs to restrict `allowedComponents()` overrides one method, not four. "Locked" deliberately covers both removal and reordering as a single axis rather than two independent flags — a component that can be freely reordered but never removed is a rare enough case not to warrant its own concept yet; if that changes, it's a small, additive change to this same class, not a redesign.

`regions()` and `requiredContext()` are intentionally not part of this v1 contract. Regions depend on a broader composition-root concept this plan explicitly defers (§4); required application context is already handled at the Component level (`RequiresContext`) and at the `renderPage()` call site (§5), and duplicating it on the Page Definition would be two ways to say the same thing.

### What this replaces

- **Section** — no change in behavior; becomes the default (no declared capabilities) case of Component.
- **Module** — dropped as a registration-time concept entirely. Application-aware behavior is `RequiresContext` when it needs context supplied, or simply ordinary code inside `with()`/the constructor when it doesn't (resolving its own data from a stored ID is not a capability the registry needs to know about at all).
- **Layout** — becomes `WrapsContent`, a declared capability rather than a fourth registered concept, and is not exercised at all in v1 (§4).
- **Template** — becomes **Page Definition**, kept as a genuinely separate, non-rendering concept rather than folded into Component, because it answers a different question ("what may this page contain") than a Component ever answers ("what does this render as").

---

## 9. Developer API

```php
PageBuilder::component(Hero::class);
PageBuilder::component(ProductGrid::class);
PageBuilder::component(MarketingLayout::class);   // an ordinary component that happens to declare WrapsContent
PageBuilder::pageDefinition(CollectionPage::class);
```

One registration verb for every renderable thing, plus one separate verb for the one non-renderable concept. No `section()`/`module()`/`layout()`/`template()` split — that would have reintroduced exactly the false equivalence §8 argues against.

**Where metadata lives:** static methods on the component class, following the existing, proven `schema(): Schema` convention — not PHP attributes, not a separate registration-builder DSL.

The required interface stays minimal:

```php
interface Composable
{
    public static function schema(): Schema;
}
```

Richer metadata (icon, category, description, preview image) is an **optional** second interface, so existing and simple components never need to implement it:

```php
interface HasComponentMeta
{
    public static function meta(): ComponentMeta; // handle, name, description, icon, category, previewImage
}
```

`ComponentMeta` is a small readonly DTO. If a registered class doesn't implement `HasComponentMeta`, the registry derives sane defaults. This directly satisfies "keep ordinary Laravel class components usable where possible" — a one-method component from the current test suite continues to work unmodified.

Capability interfaces (`WrapsContent`, `AcceptsChildren`, `RequiresContext`) are likewise optional and additive — a component implements only the ones that describe it.

---

## 10. Component discovery and scaffolding

```bash
php artisan make:page-component Hero
php artisan make:page-definition CollectionPage
```

One generator for the one renderable concept, one for the one non-renderable concept — collapsed from the previous draft's four (`make:page-section`/`make:page-module`/`make:page-layout`/`make:page-template`), for the same reason §8 collapses the registration model itself.

Generated, by default, under `app/PageBuilder/{Components,Definitions}` with matching views under `resources/views/page-builder/components` (a Page Definition has no view) — both roots configurable. A component stub extends `Illuminate\View\Component implements Composable` with a starter `schema()`.

**Discovery vs. explicit registration — recommendation: both, cache-backed**, unchanged from the previous draft. Discovery scans the configured directories for classes implementing `Composable` or extending `PageDefinition`, writing the result to a cached manifest (`php artisan page-builder:cache`) rather than reflecting over it on every request. In `APP_ENV=production` with no manifest present, discovery **fails loudly** rather than silently falling back to a live filesystem scan. Explicit `PageBuilder::component()`/`pageDefinition()` calls in a service provider are unioned with whatever discovery finds.

---

## 11. Rendering architecture

Today, `SectionRenderer` does one thing directly: `new $componentClass(...$props)` followed by `render()->with($data)->render()`, concatenated into an HTML string. That is Blade-only by construction, not by a documented boundary.

**Proposed seam — a driver contract, one implementation:**

```php
interface RendererDriver
{
    public function renderComponent(string $componentClass, array $props): string;
    public function supports(string $componentClass): bool;
}
```

`BladeRendererDriver` is today's exact logic, extracted verbatim (Migration Stage D, §20) — no behavior change.

**Why Livewire sections and Inertia are not claimed as supported, and what a real path looks like:** unchanged from the previous draft. A `Livewire\Component` doesn't share `Illuminate\View\Component`'s API and needs a real mount/hydrate lifecycle that "instantiate, call render, concatenate the string" cannot provide — a credible `LivewireRendererDriver` requires the entire public rendering pipeline to move from a concatenated string to a compiled Blade view, a genuine Milestone 4 architectural change. Inertia is architecturally harder still — its whole-page component-plus-props response model is a different shape of problem entirely, needing its own dedicated design spec before any code is written. Neither is claimed as working until it demonstrably is.

**How the pieces fit together**, extended for the corrected model:

- _Page resolution_: for a routable page, site (via `SiteResolver`) + stored `path` → single indexed lookup (§5). For a host-embedded composition, the host's own route resolves its own model and calls `renderPage()` directly (§5).
- _Component context resolution_: `renderPage($page, context: [...])` merges the caller-supplied `$context` array into the props passed to any component in the tree that declares `RequiresContext` and lists a matching key. A component that both stores editorial props (a selected collection ID, a display style) and requires live context (the currently viewed `Collection`, to show "related to what you're looking at") receives both, merged, at construction — the stored configuration is never enough on its own to fully render such a component, and that's expected, not a gap.
- _Nested composition_: a component declaring `AcceptsChildren` renders its own children through the same renderer, recursively — the same technique used for the top-level page, applied one level deeper. No separate mechanism.
- _A wrapping component's slot_: applies only where `WrapsContent` is actually used (not in v1, §4) — the wrapping component's own render receives the rest of the composition's output as its slot.
- _Component prop validation_: each `FieldTypeDefinition::validate()` (§18), replacing today's `filterPropsAgainstSchema()`'s inline per-type logic.
- _Assets_: components' own CSS/JS remain entirely the host's build concern; the package's own admin assets are separate (§16).
- _Preview vs. published_: identical resolution and driver, different data source (`page_sections` vs. latest `page_revisions.snapshot`) — unchanged.
- _Failure handling_: for visitors, unchanged — catch, log, skip, never fatal the page. This now covers two distinct failure shapes, both handled the same way: a component that throws while rendering, and a component whose `requiredContext()` key was never supplied. For editors, the same renderer call, invoked with an "admin context" flag, returns which components failed and _why_ — a render exception versus missing required context are different messages, both surfaced, neither silent.

---

## 12. Settings architecture

Unchanged from the previous draft. **Deployment-level** (published `config/page-builder.php`): `admin.prefix`, `admin.middleware`, `disks.uploads`, `component_paths` (discovery directories), `revisions.retention`, `assets.strategy`.

**Database-level** (`site_settings`, §4): `name`, `homepage_page_id`, `default_meta_title`, `default_meta_description`, `logo_media_id`, `favicon_media_id`, `default_social_image_media_id`, `robots_index`, `head_scripts`, `body_scripts`.

**Type-safe API, not string keys:**

```php
$settings = PageBuilder::settings(); // SiteSettings value object
$settings->name;
$settings->homepage();   // resolves the Page
```

A new setting requires a migration and a new typed property, on purpose.

---

## 13. Navigation

Unchanged from the previous draft. Two tables (§4): `navigations` and `navigation_items`.

```php
$nav = PageBuilder::navigation('main'); // ordered tree of resolved items
```

Each item's effective URL is computed at render time from its linked page's current `path`. A navigation item can only usefully point at a page that _has_ a `path` (§4) — the admin's page picker for navigation items should only offer routable pages, since a host-embedded page has nothing for a nav item to link to. If a linked page is deleted, `navigation_items.page_id` is set null and the admin flags the item as a broken link.

Rendering stays host-owned by default: the package hands back the resolved tree; an optional `<x-page-builder::navigation handle="main" />` convenience component exists but is not the primary contract.

---

## 14. Media and assets

Unchanged from the previous draft. One `media` table (§4) plus a simple grid picker modal wired into the Image field type. No folders, no collections, no tagging, no image-transformation-variant table, no external-media embedding.

---

## 15. Installation system

Unchanged from the previous draft. `php artisan page-builder:install` performs only genuinely safe, additive operations (publish config, publish compiled assets, ask a small number of interactive questions, create the initial `Site` row, optionally create a first page) and documents everything that would otherwise mean editing a file the host application already owns (routing snippet, migration reminder, no Tailwind step needed, no user/permission setup).

---

## 16. Package assets

Unchanged from the previous draft. Replace CDN-loaded SortableJS with a small, versioned bundle built by the package itself, published via `vendor:publish` into `public/vendor/page-builder/`, self-hosted, offline-capable, CSP-friendly. The admin's CSS is a package-owned compiled Tailwind build, not scanned from the host's `content` globs.

---

## 17. Authentication and authorization

Unchanged in mechanism from the previous draft — middleware config, permissive-default Gates (`page-builder.pages.edit`, `page-builder.pages.publish`, `page-builder.settings.manage`, `page-builder.navigation.manage`), a configurable `current_user_id_resolver` instead of hardcoded `auth()->id()` calls. **What changes is the stakes**: an authenticated account-dashboard composition surface is a materially more sensitive context than a public marketing page, which is exactly why the publish/edit ability split and the permissive-but-overridable Gate design matter more now than they did under the narrower marketing-page framing — not a reason to change the mechanism, a reason to take getting the default right seriously.

---

## 18. Extensibility

**The single most consequential change in this plan is the Component/Page Definition model itself (§8)** — not, as the previous draft argued, the `FieldType` registry alone. Both matter, but the Component model is what makes the corrected product vision architecturally possible at all; the `FieldType` registry is what makes it extensible once it exists.

```php
interface FieldTypeDefinition
{
    public function view(): string;                              // Blade partial or component name
    public function validate(mixed $value, Field $field): mixed;  // coercion + whitelisting, per type
    public function default(): mixed;
}

PageBuilder::fieldType('color', ColorFieldType::class);
```

The 8 existing types ship as the package's own built-in `FieldTypeDefinition` implementations, registered by default — this is where today's `filterScalarValue()`/`filterLinkValue()`/`isAllowedUrlScheme()` logic relocates to. `Field`'s public shape is preserved.

**Other deliberate extension points**, kept restrained rather than making every internal class replaceable:

- **Custom page models**: resolved via the container or a configurable class in config, the same pattern Cashier/Sanctum use.
- **Component metadata**: `HasComponentMeta` (§9).
- **Component capabilities**: `WrapsContent`, `AcceptsChildren`, `RequiresContext` (§8, §9).
- **Renderer drivers**: `RendererDriver` (§11).
- **Page/site resolution**: `SiteResolver` (§5).
- **Publishing hooks**: events (§19) plus the Actions remain directly container-bindable.
- **Asset handling**: `MediaUploader` bound as an interface.

**Explicitly not part of the extension surface for now**: a plugin system for arbitrary third-party admin pages, and a "register your own settings page" mechanism.

---

## 19. Events and lifecycle hooks

Unchanged from the previous draft. Ship `PagePublishing` (cancellable), `PagePublished`, and `RevisionRestored`. Skip `PageCreating`/`PageSaving`/`PageSaved` as noise or better served by container-overriding an action. Defer `PageUnpublishing`/`PageUnpublished` until an explicit unpublish action exists, and `SectionAdded`/`SectionRemoved` (renamed in spirit to `ComponentAdded`/`ComponentRemoved` if ever built) until a real use case appears. The publish-gating rationale for `PagePublishing` is, if anything, stronger now that publish can affect an authenticated or ecommerce surface, not weaker.

---

## 20. Migration from the current code

A staged path, each stage independently shippable and test-covered, with no period where the suite is red across a commit boundary.

**Stage A — FieldType registry.** Unchanged from the previous draft. Objective: replace the closed enum with the registry from §18, zero behavior change for the 8 existing types. Files: `src/Schema/FieldType.php` → new `src/FieldTypes/{FieldTypeDefinition.php,FieldTypeRegistry.php,Types/*.php}`. Compatibility: watch the two call sites in `src/Livewire/PageEditor.php` comparing against `FieldType::Repeater`/`FieldType::Group` directly. Existing tests that must keep passing unmodified in behavior: `tests/Unit/SchemaTest.php`, `tests/Feature/FieldPartialsTest.php`, `tests/Feature/PropTamperingRegressionTest.php`. New tests: `FieldTypeRegistryTest`, a custom-field-type end-to-end regression test.

**Stage B — Sites and page hierarchy.** Objective: add `sites`, and `pages.site_id`/`parent_id`/`path`(nullable)/`definition` without breaking existing model behavior. Migrations: `create_sites_table`, `add_site_and_hierarchy_columns_to_pages`, a backfill migration attaching every existing `Page` to one default `Site`. Compatibility: `tests/TestCase.php`'s database setup needs a `Site` fixture. New tests: hierarchy creation, `path` uniqueness scoped to a site, homepage resolution, and a page created with a null `path` correctly absent from public-route resolution.

**Stage C — Component registry facade.** Objective, revised from the previous draft's "Sections/Modules umbrella": rename `Sectionable` → `Composable`, introduce `PageBuilder::component()` and `PageBuilder::pageDefinition()` behind a `PageBuilder` facade, without removing the existing `config('blade-page-builder.sections')` array path — union both sources. Files: new `src/PageBuilder.php`; `SectionRegistry` evolves internally but keeps its exact public `resolve()`/`slugs()` shape; new `src/Contracts/PageDefinition.php` and a corresponding registry. Definition of done: both registration styles work side by side; existing registry test passes unmodified; new facade-registration and Page-Definition-registration tests pass.

**Stage D — Renderer driver seam.** Unchanged from the previous draft. Objective: extract `SectionRenderer`'s current logic into `BladeRendererDriver` behind `RendererDriver`, zero behavior change. Compatibility: `tests/Feature/SectionRendererTest.php` and `tests/Feature/RenderPageComponentTest.php` must pass completely unmodified.

**Stage E — Admin route/asset scaffold and `page-builder:install`.** Unchanged from the previous draft. Objective: package-owned admin route group, compiled asset publishing, install command — deliberately without yet decomposing the editor UI. Compatibility: `tests/Feature/PageEditorMountPointTest.php` should keep passing unmodified.

**Stage F — Editor decomposition.** Unchanged in approach from the previous draft. Objective: split `PageEditor` into the components from §7, one extraction at a time, migrating the relevant existing tests alongside each single extraction.

**Stage G — Minimal Page Definition enforcement (new).** Objective: `ComponentList`/`PageSettingsPanel` respect `allowedComponents()`/`requiredComponents()`/`lockedComponents()`/`defaultComponents()` for a page's chosen `PageDefinition` — the "Add" picker filters to what's allowed, locked components lose their delete/reorder controls, missing required components surface as a warning. This depends on Stage C (the registry existing) and roughly parallels Stage F (both touch the editor UI), and — unlike the previous draft's treatment of "Templates" — is pulled into the same near-term work as the rest of Milestone 1, not deferred to Milestone 2/3. New tests: allowed/required/locked enforcement, default-arrangement seeding on page creation.

Domain additions beyond the current code's scope (navigation, richer settings, media) remain Milestone 2 work, covered at milestone grain in §21.

---

## 21. Release roadmap

### Milestone 0 — Architecture correction

**Included:** Stages A–D (FieldType registry, sites/hierarchy schema with nullable `path`, Component registry facade with the `Composable` rename and `PageDefinition` registry, renderer driver seam).
**Excluded:** any new admin screens; no navigation/media/settings tables yet; no Page Definition _enforcement_ in the editor UI yet (that's Stage G, Milestone 1) — Milestone 0 only needs the registry and the model to exist.
**Dependencies:** none.
**Acceptance:** full test suite green; a host can register a custom field type and a custom component via `PageBuilder::component()`; a page can belong to a site and a parent, with an optional `path`; `SectionRenderer`/`RendererDriver` behavior is provably unchanged.

### Milestone 1 — Installable composition foundation

**Included:** Stage E (admin routes, install command), Stage G (minimal Page Definition enforcement — **reprioritized into Milestone 1 from the previous draft's Milestone 2/3**, because ecommerce/application-aware guardrails are now core to the product, not a stretch goal), page index/creation/settings screens with a Page Definition picker, public routing (homepage + nested paths + host-embedded pages via `renderPage()`), package-compiled assets, the minimal `media` table and image picker, minimal `site_settings`, Gate-based auth hooks with permissive defaults.
**Excluded:** navigation, `WrapsContent`/`AcceptsChildren` exercised in the editor UI (the capabilities exist in the model from Milestone 0, but nothing in v1 needs them rendered yet), autosave-indicator polish, signed external preview links.
**Dependencies:** Milestone 0 complete.
**Acceptance:** `composer require` → `page-builder:install` → `migrate` → register a component (and, if needed, a Page Definition) → create and publish a page that respects that definition's guardrails → view it live — entirely through package-owned screens, in roughly the target ten minutes.

### Milestone 2 — Complete visual composition workflow

**Included:** Stage F (editor decomposition) with drag/duplicate/delete, broken-component and missing-context warnings, the unpublished-change indicator, responsive and full-page preview, visible validation errors; the navigation system end to end; fuller settings (logo/favicon/social image); paginated revision history; `WrapsContent` exercised as a real, selectable capability if more than one wrapping component is registered; signed preview links; publish/edit Gate separation.
**Excluded:** `AcceptsChildren` with real nested drag-and-drop UI (the rendering seam exists from Milestone 0; the editor experience for it is not yet built); scaffolding commands; any field type beyond Milestone 0's registry contents.
**Dependencies:** Milestone 1.
**Acceptance:** the editor satisfies every item in §7's checklist; no single Livewire component holds more than one clear responsibility.

### Milestone 3 — Ecosystem and extensibility

**Included:** `make:page-component`/`make:page-definition` generators plus discovery/caching (§10); nested-composition editor UI for `AcceptsChildren`; richer `HasComponentMeta` (categories, search in the picker); shipping `PagePublishing`/`PagePublished`/`RevisionRestored` (others only if real demand appears); swappable models via config; documentation aimed at third-party consumers.
**Excluded:** a second renderer driver; global sections/reusable patterns; a full DAM.
**Dependencies:** Milestone 2.
**Acceptance:** a developer outside this project could plausibly install and extend the package using only published documentation.

### Milestone 4 — Additional frontend renderers

**Included:** Livewire component lifecycle support (requires moving the public rendering pipeline from a concatenated HTML string to a compiled Blade view — a genuine architectural change, §11) and a credible Inertia/Vue or Inertia/React adapter.
**Excluded from this plan entirely** beyond identifying the seam.
**Dependencies:** Milestone 3, plus a future dedicated design spec for the Inertia adapter specifically.

---

## 22. First implementation slice

Recommendation, revised from the previous draft to reflect the reprioritized Page Definition work: the first slice is Milestone 0 in full, plus the earliest sliver of Milestone 1 that makes the foundation — including its guardrail mechanism, which is now core rather than a stretch goal — visible rather than purely internal plumbing.

**Sequence:**

1. **FieldType registry** (Stage A) + tests.
2. **Sites and page hierarchy schema**, including nullable `path` and the `definition` column (Stage B) + tests.
3. **Component registry facade**, including the `Composable` rename and `PageBuilder::component()`/`pageDefinition()` (Stage C) + tests — needed before either "register a component" or "define a Page Definition" can be demonstrated.
4. **Renderer driver seam** (Stage D) + tests.
5. **Admin route group and `page-builder:install`** (Stage E, routes and install command only) + tests.
6. **Page index screen** + tests.
7. **Page creation and page settings screen**, including the Page Definition picker at creation (Stage E/G) + tests — retires both the "no page-metadata editing UI" gap identified in the earlier due-diligence pass on this codebase, and demonstrates the guardrail mechanism the corrected vision now treats as core.
8. **Minimal Page Definition enforcement** in the (still monolithic) editor — allowed/required/locked components (Stage G) + tests.
9. **Public routing**, including a page with a null `path` correctly being unreachable via the catch-all (Stage B/E) + tests — closes the loop end to end.

**Explicitly out of scope for this first slice**: package asset compilation (CDN SortableJS survives one more slice), the `media` table and picker, navigation, full editor decomposition (the monolithic `PageEditor` is reused as-is behind the new admin route, with Page Definition enforcement grafted onto it), settings beyond bare name/homepage, and auth Gates beyond middleware configuration.

---

## Reconciliation appendix

This plan was revised once, after `docs/product-vision.md` corrected the product's audience and category from "a marketing-website page builder" to "a visual composition layer for existing Laravel applications, public and application-aware alike." This appendix summarizes exactly what changed as a result.

**Decisions changed:**

- The Component model collapses Sections, Modules, and Layouts into one `Composable`-based concept described by optional capabilities (`WrapsContent`, `AcceptsChildren`, `RequiresContext`), registered through a single `PageBuilder::component()` verb, rather than three (eventually four) separate registration systems.
- "Template" is renamed **Page Definition**, kept as a genuinely separate, non-rendering concept (not folded into Component), and its capability set is expanded (allowed/required/locked/default components) to match the ecommerce guardrail use case now explicitly in scope.
- **Page Definitions move from Milestone 2/3 into Milestone 1** (new Stage G) — the single biggest roadmap change in this revision, because placement guardrails are now a core requirement, not a later refinement.
- `pages.path` becomes **nullable/optional**, and public catch-all routing becomes opt-in per page, so a composition can be embedded inside a host-owned route (an ecommerce collection page, an authenticated account screen) without being forced to also be a standalone, slugged, publicly catch-all-routed page.
- `PageBuilder::renderPage()` gains a `context` parameter, and components gain a `RequiresContext` capability, as the mechanism for "route-backed ecommerce pages" and "sections of existing routes" — resolved without any new database table or polymorphic binding mechanism.
- The `pages.layout` column and a separate `Layoutable` contract are dropped entirely for v1; wrapping content becomes a Component capability (`WrapsContent`) that v1 doesn't need to exercise at all, because the host's own existing Blade layout already wraps composed output.
- The Composer package/namespace rename (to `wearepixel/page-builder` / `WearePixel\PageBuilder`) is **no longer recommended for Milestone 0** — naming is explicitly unsettled per the vision document, and committing to it now would contradict that.
- The internal `Sectionable` → `Composable` rename is **newly recommended**, independent of the product's eventual public name.
- The audience and product-boundary language throughout (§2, §3, §6) is broadened from "marketing pages" to "any developer-exposed frontend experience," with an explicit, sharpened distinction from Filament/Nova (§3) that the previous draft only implied.
- The single most consequential change in the plan is re-ranked from "the `FieldType` registry" to "the Component/Page Definition model" (§18) — the `FieldType` registry remains important, but it extends a model that itself had to change first.

**Decisions retained:**

- The `sites` table, introduced now with a single default row and no multi-site UI.
- The `FieldType` registry replacing the closed enum, and its migration approach (Stage A).
- The three-table draft/publish/revision model (`pages`/`page_sections`/`page_revisions`), unchanged in shape.
- The renderer driver seam (`RendererDriver`), and the non-claim that Livewire or Inertia rendering is supported yet.
- The editor decomposition plan (§7), the settings/navigation/media architecture (§12–§14), the installation system's safe/documented split (§15), the package-asset strategy (§16), and the Gate-based auth model (§17) — none of these were narrowed by the marketing-page framing in the first place, so none needed correcting.
- The overall five-milestone shape, with Milestone 1's acceptance bar still "install → register → compose → publish → view live in about ten minutes," now demonstrated against a guardrail-bearing page rather than a bare marketing page.

**Decisions deferred (unchanged from the previous draft, restated for completeness):**

- Multi-site/multi-tenant admin UI, custom domains, any hosted/SaaS product.
- Navigation, richer settings (logo/favicon/social image), to Milestone 2.
- Global/reusable sections or patterns, a full digital asset manager, to Milestone 3+ or later, only on demonstrated demand.
- `WrapsContent` and `AcceptsChildren` exercised as real editor-facing features (the model supports them from Milestone 0; the UI for them is Milestone 2/3).
- A second renderer driver (Livewire, Inertia) and any non-page composition root ("Surface"/"Region"), to Milestone 4 and beyond respectively — the latter is explicitly a seam to preserve, not a feature to build.

**Unresolved decisions requiring sign-off:**

1. **Pulling Page Definitions into Milestone 1** is the largest scope change in this revision. Confirm this is the right trade — it adds real work to the first release in exchange for the guardrail capability the ecommerce use case needs from day one.
2. **The public package/product name stays unresolved.** Fixture, Lattice, and Inlay are shortlisted in `docs/product-vision.md`, with Fixture recommended, but nothing is decided. This plan proceeds using `PageBuilder` as a neutral facade name and the existing `wearepixel/blade-page-builder` Composer name as a placeholder — confirm that's acceptable to keep building against until naming is settled separately.
3. **Confirm the `Composable` rename** (from `Sectionable`) is worth doing now, given it touches every existing component and the current test suite, even though it's mechanical and low-risk.
4. **Confirm `WrapsContent` needing no v1 implementation is correct** — that every anticipated first-release host application is genuinely content wrapping composed output in its own existing Blade layout, with no near-term need for an editor-selectable page shell.
5. Everything carried over from the previous sign-off list (the `sites` table now, the `FieldType` registry replacement, the compiled admin asset build, and the foundation-first sequencing in §22) still stands and is not being re-litigated here.

---

## Recommended destination

This repository becomes an open-source Laravel package that a developer installs into an existing custom application to get a self-contained, database-backed visual composition layer — owning its own admin interface, routing, publishing, and revisions — while every unit of rendered content stays a real PHP/Blade component the developer wrote and controls, and every unit of business data stays exactly where it already lived. Never generated markup stored as canonical content, never business data copied into the builder to display it, and never a requirement that the host application reorganise itself around the package.

## What not to build yet

- Multi-site/multi-tenant admin UI (the `sites` table is a schema seam only, not a feature)
- Custom domains, or any hosted/SaaS product
- Billing or tenancy UI of any kind
- Global/reusable sections or a "patterns" library
- A full digital asset manager — folders, tagging, transformation variants, external media
- Page Definitions as a visually-buildable thing (they stay code-defined, by developers)
- Livewire-component or Inertia rendering support (the seam is designed for; the drivers are not built)
- `WrapsContent`/`AcceptsChildren` as real, editor-facing features (the capabilities exist in the model from Milestone 0; building the UI for them earlier than Milestone 2/3 would be building ahead of demonstrated need)
- A generic, arbitrary-key application settings store beyond the fixed, typed CMS settings
- PHP-attribute-based component registration
- A plugin system for arbitrary third-party admin pages
- A separate, non-page composition root ("Surface"/"Region") — the seam is preserved (§4), the concept is not built
- Any installer behavior that edits `routes/web.php`, a service provider, or the host's Tailwind config

## Decisions requiring your sign-off

Carried forward and updated from the previous draft; see the reconciliation appendix above for the full list with context. In short:

1. **Introduce the `sites` table now.** Recommended: yes (unchanged).
2. **Replace the `FieldType` enum with a registry/contract.** Recommended: yes (unchanged).
3. **Rename `Sectionable` to `Composable` now**, independent of the product's public name. Recommended: yes (new).
4. **Pull minimal Page Definition enforcement into Milestone 1**, ahead of the rest of the visual-composition polish work. Recommended: yes (new, the largest scope change in this revision).
5. **Leave the Composer package/namespace name unchanged** until naming is resolved separately, rather than renaming to `wearepixel/page-builder` as the previous draft recommended. Recommended: yes (reversed from the previous draft).
6. **Build no `WrapsContent` implementation in v1**, relying entirely on the host's existing Blade layout to wrap composed output. Recommended: yes, pending confirmation that no near-term host application needs an editor-selectable page shell (new).
7. **Ship the admin with its own compiled Tailwind/JS build.** Recommended: yes (unchanged).
8. **Confirm the sequencing in §22** — foundation and guardrail model first, before the first visible admin screens. Recommended: yes (updated to include Page Definition work in the foundation phase).
