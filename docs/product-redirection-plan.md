# Product Redirection Plan — from page-builder engine to installable Laravel CMS

Date: 2026-08-02
Status: Decision-ready — no implementation started
Supersedes nothing: `docs/superpowers/specs/2026-07-10-blade-page-builder-design.md` remains the historical record of why the current foundation exists and is not being deleted or rewritten.

## Why this document exists

The current repository is a working, tested, schema-driven page-building **engine**. It is not yet the **product**: an open-source, Laravel-native visual website builder that a developer installs into an existing application and immediately has a complete database-backed CMS, without building an admin shell, page screens, routing, or publishing controls themselves.

This document inspects the actual code that exists today, classifies what should survive unchanged, evolve, or be replaced, and lays out a concrete, staged path to the target product. It does not begin implementation. Every recommendation is grounded in specific files and classes in this repository, not generic CMS wisdom.

---

## 1. Current foundation

| Part                                                                                                | File(s)                                          | Classification                                    | Why                                                                                                                                                                                                                                                                                                                                                                                                                          |
| --------------------------------------------------------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Page`, `PageSection`, `PageRevision` models                                                        | `src/Models/*.php`                               | **Evolve**                                        | The shape (final classes, `array` casts on JSON columns, an enum cast for status) is correct and proven by 32 passing tests. They need new columns (site scope, hierarchy, layout, template) and new relationships, not a rewrite.                                                                                                                                                                                           |
| `pages`, `page_sections`, `page_revisions` tables                                                   | `database/migrations/2025_01_01_*.php`           | **Evolve**                                        | The three-table split (live draft / append-only publish log) is the right shape for the fuller product too — see §4. They gain columns and siblings (sites, settings, navigation, media), they are not replaced.                                                                                                                                                                                                             |
| `PageStatus` enum                                                                                   | `src/Enums/PageStatus.php`                       | **Retain**                                        | `Draft`/`Published` is sufficient for the target product's v1. No change proposed.                                                                                                                                                                                                                                                                                                                                           |
| `Schema` / `Field` fluent builder                                                                   | `src/Schema/Schema.php`, `src/Schema/Field.php`  | **Evolve**                                        | The builder pattern (`Schema::make()->text()->select()->mapsToClass()`) and the readonly `Field` DTO are exactly the right developer experience and should not change shape. What changes underneath is what `Field::$type` points at — see the next row.                                                                                                                                                                    |
| `FieldType` enum                                                                                    | `src/Schema/FieldType.php`                       | **Replace**                                       | This is the one clear architectural break in the whole plan. A closed, backed enum with 8 hardcoded cases is precisely what §18 (Extensibility) says must not remain: a host application cannot register a 9th field type without patching this package. It becomes a registry of `FieldTypeDefinition` implementations — see §18.                                                                                           |
| `Sectionable` contract                                                                              | `src/Contracts/Sectionable.php`                  | **Evolve**                                        | The one-method contract (`static function schema(): Schema`) stays exactly as-is and stays the minimum bar for a "section." It becomes the base that `Moduleable` extends (§8) rather than being replaced.                                                                                                                                                                                                                   |
| `SectionRegistry`                                                                                   | `src/Support/SectionRegistry.php`                | **Evolve**                                        | `resolve()`/`slugs()` and the config-array-backed singleton pattern are correct and already regression-tested (`tests/Unit/SectionRegistryTest.php`). It becomes the internals of a broader component registry reachable through a `PageBuilder` facade, but its public method names and behavior (throw `InvalidArgumentException` on an unknown slug) are preserved, not deleted.                                          |
| `SectionRenderer`                                                                                   | `src/Support/SectionRenderer.php`                | **Evolve**                                        | The resolve → instantiate → `render()->with($data)->render()` → catch-and-log-per-section logic is correct and is the single best piece of defensive design in the codebase. It is extracted behind a `RendererDriver` interface (§11) as `BladeRendererDriver`, verbatim, so this exact behavior is preserved rather than reimplemented.                                                                                    |
| `PublishPage`, `RestorePageRevision` actions                                                        | `src/Actions/*.php`                              | **Retain**                                        | Transactional, single-purpose, non-destructive restore (`RestorePageRevision` always calls `PublishPage` again rather than rewinding history). This is correct and stays. Only additions are event dispatch (§19) around the existing `execute()` calls.                                                                                                                                                                     |
| Draft/publish/revision model                                                                        | (behavior, not one file)                         | **Retain**                                        | `page_sections` as the one live draft tree, `page_revisions` as an append-only whole-tree snapshot log, is the right foundation for the fuller product and is not being redesigned.                                                                                                                                                                                                                                          |
| `PageEditor` Livewire component                                                                     | `src/Livewire/PageEditor.php`                    | **Replace (as a monolith), retain (as behavior)** | At ~300 lines it already owns section CRUD, drag-order persistence, prop filtering/validation, image upload, publish, and restore — six responsibilities in one class. §7 decomposes it into an orchestrator plus five focused child components. Every operation it currently performs is preserved; only its shape as a single component changes.                                                                           |
| `filterPropsAgainstSchema()` / `filterLinkValue()` / `filterScalarValue()` / `isAllowedUrlScheme()` | `src/Livewire/PageEditor.php`                    | **Retain, relocate**                              | This is the package's real security boundary (regression-tested in `tests/Feature/PropTamperingRegressionTest.php`) and must not be diluted while `FieldType` becomes a registry. Its logic moves onto `FieldTypeDefinition::validate()` per type (§18) so each field type owns its own coercion rule, but the net behavior — undeclared keys dropped, values coerced to type, Link URLs allowlisted — is preserved exactly. |
| Field partials (`resources/views/fields/*.blade.php`)                                               | 8 files                                          | **Evolve**                                        | The one-partial-per-type, resolved-by-convention pattern is correct and becomes the seed of the field-type registry's `view()` contract (§18) — a custom field type registers its own partial the same way.                                                                                                                                                                                                                  |
| `page-editor.blade.php` fixed 3-column layout                                                       | `resources/views/livewire/page-editor.blade.php` | **Replace**                                       | Superseded by the package-owned admin screens in §6/§7. The _editing operations_ it wires up (drag via SortableJS, per-field `wire:model`) are retained; the specific 3-column Blade view is not.                                                                                                                                                                                                                            |
| CDN-loaded SortableJS                                                                               | inline `<script>` in the view above              | **Replace**                                       | See §16 — this is a supply-chain and offline-reliability liability that must not ship in the target product.                                                                                                                                                                                                                                                                                                                 |
| `config/blade-page-builder.php`                                                                     | `config/blade-page-builder.php`                  | **Evolve**                                        | The `sections` map and `disk` key are retained as concepts and grow into the fuller config surface in §12.                                                                                                                                                                                                                                                                                                                   |
| `BladePageBuilderServiceProvider`                                                                   | `src/BladePageBuilderServiceProvider.php`        | **Evolve**                                        | Config merge, migration loading, view loading, Blade component registration, and Livewire namespace registration are all correct and stay. It gains conditional admin route registration, asset publishing, and command registration.                                                                                                                                                                                        |
| Test suite (32 tests, 108 assertions)                                                               | `tests/**`                                       | **Retain as the regression baseline**             | Nothing here is discarded. Some tests (`PageEditorLayoutTest`, `PageEditorMountPointTest`, `PageEditorHistoryTest`) will need to move or be adapted as the editor is decomposed and reached through a package route instead of a bare component tag (§20, Stage F), but their _assertions about behavior_ — history restore works, the mount tag renders — are what must keep passing, not their literal current form.       |

One naming note surfaced by inspecting the current `composer.json` against the target product's own example commands: the repository is currently `wearepixel/blade-page-builder` / namespace `WearePixel\BladePageBuilder`, while every example in the target brief (`composer require wearepixel/page-builder`, `PageBuilder::routes()`, `make:page-section`) uses the shorter `page-builder` name. **Recommendation: rename** the Composer package to `wearepixel/page-builder` and the namespace to `WearePixel\PageBuilder` as part of Milestone 0. It is a mechanical, low-risk, high-clarity change (the current name over-specifies "Blade" as if it were the only possible renderer, which §11 explicitly says is not the long-term claim), and doing it while the package is still pre-release and uninstalled anywhere avoids ever shipping the old name. This is listed as a sign-off item at the end of this document.

---

## 2. Target product experience

The bar: a competent Laravel developer, starting from a normal Laravel app, has a working visual page builder in about ten minutes.

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

This registers both the admin route group and the public catch-all (§5). A host that wants only one half calls `PageBuilder::adminRoutes()` or `PageBuilder::publicRoutes()` instead.

### Visit the admin

```
http://app.test/admin/pages
```

(prefix configurable at install time). Protected by whatever middleware was chosen during install — the package assumes nothing about the host's auth package and ships a permissive default (§17) so this works immediately even before the host has configured anything further.

### Create the first page

In the admin: **New Page** → title "Home" → toggle "Set as homepage" → **Create**. Lands in the page editor with an empty section list.

### Generate the first custom section

```bash
php artisan make:page-section HeroSplit
```

Generates `app/PageBuilder/Sections/HeroSplit.php` (a stub `Illuminate\View\Component` implementing `Sectionable`, with a starter `schema()`) and `resources/views/page-builder/sections/hero-split.blade.php`.

### Register (or let it be discovered)

If it lives in the default scanned directory (`app/PageBuilder/Sections`), nothing further is required — component discovery (§10) picks it up. To register something outside that convention (a vendor package's own sections, a non-default namespace), in `AppServiceProvider::boot()`:

```php
use WearePixel\PageBuilder\Facades\PageBuilder;
use App\PageBuilder\Sections\HeroSplit;

PageBuilder::section(HeroSplit::class);
```

### Compose and publish

Back in the admin editor: **Add section** → "Hero Split" → edit its fields with the live preview updating alongside → **Publish**.

### View it live

```
http://app.test/            (resolved as the configured homepage)
http://app.test/about/team  (a nested page, if hierarchy was used)
```

Rendered by the package's public catch-all route using the exact same `SectionRenderer` the admin preview used — no separate production renderer to diverge from the preview (§11, carried forward unchanged from the current `RenderPage` design).

---

## 3. Product boundaries

**The package owns:** admin interface, page CRUD, page metadata, page hierarchy, public page resolution, publishing, revisions, preview, CMS-level settings, navigation, section/module/layout/template registration, package assets, installation and scaffolding commands.

**The host application owns:** application-specific business models, the visual implementation of every section/module it registers, its own auth/middleware decisions, application-specific dynamic data, deployment and infrastructure.

Three places in this split need pushing back on rather than accepting as given:

- **"Application-specific dynamic data" is not cleanly host-only.** A module (§8) is, by definition, a package-registered component that reaches into host data. The honest boundary is: the package owns the _registration and rendering contract_ a module fulfils (same `Sectionable`-derived interface, same `SectionRenderer` pipeline, same schema-driven editable configuration), while the host owns the _data-fetching code inside that module's own class_. Neither side owns "dynamic data" outright.
- **"Custom middleware and authentication decisions" is not cleanly host-only either.** The package must own _how_ configured middleware gets attached to its own route group (that is package plumbing — see §5), even though _which_ middleware/guard is chosen is entirely the host's call. Framing this as purely "host responsibility" undersells the package's job of applying that choice correctly and predictably.
- **Settings needs a narrower boundary than "the package owns settings."** The package should own a small, fixed, typed set of CMS-relevant settings (site name, homepage, default meta, logo/favicon/social image, robots behaviour, head/body scripts — see §12). It must not become a general-purpose host application settings store (an arbitrary-key EAV table that any part of the host app reaches into for unrelated config). That would be scope creep into a different kind of package entirely, and directly conflicts with the "type-safe settings API" requirement, which only works for a closed, known set of fields.

Navigation deserves one clarification, not a correction: the package owns navigation _structure, storage, and CRUD_, but per "no lock-in," it does **not** own navigation _visual rendering_ by default. It exposes a resolved tree; the host's own Blade layout decides how to draw it (an optional convenience component is offered, but it is sugar, not the contract — see §13).

---

## 4. Domain model

### Sites — explicit recommendation

**Introduce a `sites` table now**, even though the first usable release exposes exactly one site and no multi-site UI. Reasoning: `pages` currently has no tenant/site scope at all. Retrofitting a `site_id` foreign key onto `pages`, and onto the new `settings`/`navigations` tables, after real installations exist is a breaking migration for every adopter. Adding it now — while the package has zero production installs — costs nothing beyond one extra join and one auto-created default row, and is exactly the kind of seam that makes future multisite, localisation, or a hosted product (explicitly _not_ being built now, see the closing sections) possible later without an architectural rewrite. This mirrors the project's own prior instinct, visible in the original design spec's explicit rejection of the "Foundry" scaffold-and-diverge model in favour of staying centrally maintained: don't paint the schema into a corner while it's free not to.

### Proposed tables

| Table                     | Purpose                      | Significant columns                                                                                                                                                                                                                                                                    | Relationships                                                                                                 | v1 or deferred                                                                                                                                                  |
| ------------------------- | ---------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `sites`                   | Tenant/site scope seam       | `id`, `name`, `domain` (nullable), `is_default` (bool)                                                                                                                                                                                                                                 | `hasMany` pages, navigations, one `site_settings` row                                                         | **Required for v1** (schema only — no multi-site UI)                                                                                                            |
| `pages` (evolve)          | Canonical page record        | adds `site_id` FK, `parent_id` FK nullable (self-referential), `path` (stored/denormalized full path, e.g. `about/team`), `layout` (nullable string handle), `template` (nullable string handle) to the existing `title`/`slug`/`status`/`meta_title`/`meta_description`               | `belongsTo` site, `belongsTo` parent (self), `hasMany` children (self), existing `hasMany` sections/revisions | **Required for v1** — hierarchy and stored path are load-bearing for §5's routing design                                                                        |
| `page_sections` (evolve)  | Live draft section tree      | unchanged shape; no schema-level distinction between "section" and "module" rows — that split is a code-level interface distinction (§8), not a database one                                                                                                                           | unchanged                                                                                                     | **Retained as-is**                                                                                                                                              |
| `page_revisions` (evolve) | Append-only publish log      | unchanged; add a DB index on `(page_id, created_at)` if not already implied by the FK                                                                                                                                                                                                  | unchanged                                                                                                     | **Retained as-is**, pagination bug fixed in application code (§7), no schema change                                                                             |
| `site_settings`           | Typed, per-site CMS settings | `site_id` FK (unique), `name`, `homepage_page_id` (FK, nullable), `default_meta_title`, `default_meta_description`, `logo_media_id`/`favicon_media_id`/`default_social_image_media_id` (FK to `media`, nullable), `robots_index` (bool), `head_scripts`/`body_scripts` (nullable text) | `belongsTo` site, `belongsTo` homepage page, `belongsTo` media (×3)                                           | Minimal columns (`name`, `homepage_page_id`, default meta text) **required for v1**; the three media-backed columns deferred to Milestone 2 once `media` exists |
| `media`                   | Minimal asset record         | `id`, `disk`, `path`, `filename`, `mime_type`, `size`, `alt_text` (nullable), `uploaded_by` (nullable int, no FK — same pattern as today's `page_revisions.created_by`)                                                                                                                | none beyond FKs pointing at it from settings/section props                                                    | **Required for v1** (minimal — see §14)                                                                                                                         |
| `navigations`             | Named navigation trees       | `id`, `site_id` FK, `handle` (e.g. `main`, `footer`, `legal`), `name`                                                                                                                                                                                                                  | `belongsTo` site, `hasMany` navigation_items                                                                  | **Deferred to Milestone 2**                                                                                                                                     |
| `navigation_items`        | Nested nav entries           | `id`, `navigation_id` FK, `parent_id` FK nullable (self-referential), `page_id` FK nullable (`nullOnDelete`), `url` (nullable — exactly one of `page_id`/`url` set), `label`, `target`, `order`, `visible` (bool)                                                                      | `belongsTo` navigation, `belongsTo` parent (self), `belongsTo` page (nullable)                                | **Deferred to Milestone 2**                                                                                                                                     |

### Explicitly assessed and rejected (for now)

- **Page templates as database rows** — rejected. A template constrains _behaviour_ (permitted sections, default layout, locked sections) and is a code/policy concern, not editorial content. It is a plain PHP class (`Templateable`, §8/§9) referenced by `pages.template` as a string handle. No `page_templates` table.
- **Layouts as database rows** — rejected for the same reason. A registered `Layoutable` class, referenced by `pages.layout` as a string handle. v1 ships one bundled default layout the host can publish and edit directly; the pluggable _registry_ (`PageBuilder::layout()`) is deferred to Milestone 2/3 once more than one layout is a real, demonstrated need (see §8).
- **Global sections / reusable patterns** — deferred, not rejected outright. A genuinely useful feature (edit-once-update-everywhere content, or save-a-section-arrangement-as-a-starting-point), but it introduces real complexity (reference-vs-copy semantics, what a revision even means for a shared section) that has no forcing function yet. Revisit in Milestone 3+ only if real usage demands it. Listed again under "what not to build yet."
- **A generic `settings` key/value (EAV) table** — rejected in favour of the structured `site_settings` table above. An arbitrary-key settings table is exactly the "because other CMS products have them" pattern this plan is instructed to avoid, and it is incompatible with the explicit "type-safe settings API" requirement (§12).
- **Full digital-asset-manager tables** (folders, collections, tags, transformation variants) — rejected for v1. See §14.

---

## 5. Routing architecture

### Admin routes

Registered by the package inside its own route group:

- Prefix: `config('page-builder.admin.prefix', 'admin/pages')`.
- Middleware: `config('page-builder.admin.middleware', ['web'])` — the install command (§15) nudges toward adding `auth`, but the package assumes nothing about which guard.
- Route names: `page-builder.pages.index`, `page-builder.pages.create`, `page-builder.pages.edit`, `page-builder.settings`, `page-builder.navigation`, etc. — a single `page-builder.` prefix keeps every named route the package owns unambiguous against the host's own route names.

### Public routing

A single catch-all, registered **last**, so any route the host has already defined always wins:

```php
Route::get('{path?}', PublicPageController::class)
    ->where('path', '.*')
    ->name('page-builder.public.show');
```

Resolution is a single indexed lookup against the denormalized `pages.path` column — `Page::where('site_id', $site->id)->where('path', $path)->firstOrFail()` — not a recursive walk up `parent_id` per request. The empty path (`/`) resolves via `site_settings.homepage_page_id` rather than a magic empty-string page.

### The `PageBuilder::routes()` API

```php
PageBuilder::routes();          // registers both halves — the common case
PageBuilder::adminRoutes();     // admin only
PageBuilder::publicRoutes();    // public catch-all only
```

The service provider does **not** auto-register any routes on boot. Routes only exist if the host explicitly calls one of these in its own `routes/web.php` (or a provider). This is what makes "disabling package public routes" trivial — simply don't call `publicRoutes()`/`routes()` — and what makes route-conflict risk manageable: the host controls exactly where in its own route file the catch-all is registered relative to everything else.

### Custom rendering without the package's controller

For a host that wants its own controller to own the response (extra headers, a different layout wrapper, A/B logic) but still wants package-rendered content:

```php
PageBuilder::renderPage(Page $page, bool $draft = false): Htmlable
```

This is the same primitive the public controller and the admin preview both call internally (§11) — exposed directly so "custom host-controlled rendering" doesn't require reimplementing resolution or the renderer pipeline.

### Preview routes

- **In-admin preview** (today's inline preview pane, retained): no separate route needed, lives inside the already-authenticated admin middleware group.
- **Full-page/new-tab preview**: `page-builder.pages.preview` inside the admin group, same middleware as everything else.
- **Signed external preview** (share an unpublished draft with a stakeholder who isn't logged in): a `URL::signedRoute()`-generated, time-limited link outside the admin middleware group, calling the same `PageBuilder::renderPage($page, draft: true)` primitive. One rendering primitive, two access paths — not two renderers.

### Route conflicts, custom domains, multi-site

Site resolution for a request is delegated to a single swappable `SiteResolver` interface. The only implementation shipped now trivially returns the one default site. Custom domains and true multi-site routing (explicitly **not** being built now) become "write a different `SiteResolver` implementation" later, rather than a routing rearchitecture — the seam is free to add now and expensive to retrofit later, same logic as the `sites` table decision above.

---

## 6. Complete administration application

| Screen                | Purpose                                | Primary actions                                                      | Required data                                     | v1?                                                                                                   |
| --------------------- | -------------------------------------- | -------------------------------------------------------------------- | ------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Dashboard             | Orientation / landing                  | Create page, jump to recent                                          | Recent pages, counts                              | **Minimal version in v1** — a light landing page is enough; an activity/attention feed is Milestone 2 |
| Page index            | Browse and manage all pages            | Create, edit, duplicate, delete, publish/unpublish, filter by status | Paginated pages with status, `updated_at`, parent | **v1 — core**                                                                                         |
| Page creation         | Start a new page                       | Title (auto-slug), pick parent, save as draft                        | Existing pages (for parent picker)                | **v1** (template picker added once templates exist)                                                   |
| Page editor           | Compose and edit sections              | See §7 in full                                                       | Page, sections, registered components             | **v1 — core**                                                                                         |
| Page settings         | Slug, meta, hierarchy, homepage toggle | Edit and save                                                        | Page record, site settings                        | **v1** (layout/template pickers added once those registries exist)                                    |
| SEO & social metadata | Search/social presentation             | Edit title/description/social image                                  | Page record, media (for image)                    | Text fields **v1**; social image field **Milestone 2** (needs `media`)                                |
| Navigation manager    | Build/reorder nav trees                | Add item, nest, reorder, link to page or URL                         | `navigations`/`navigation_items`                  | **Milestone 2**                                                                                       |
| General settings      | Site-wide defaults                     | Edit name, homepage, default meta, logo/favicon                      | `site_settings`                                   | Minimal fields **v1**; logo/favicon **Milestone 2**                                                   |
| Revision history      | Audit and restore                      | List, restore                                                        | Paginated `page_revisions`                        | **v1** (carries forward existing feature, fixes the unbounded-query gap)                              |
| Preview               | See the draft as a visitor would       | Toggle device width, open full-page, copy signed link                | Rendered HTML via `renderPage()`                  | Inline + full-page **v1**; signed external link **Milestone 2**                                       |
| Media/asset picker    | Choose or upload an image              | Upload, search, select                                               | `media` table                                     | **v1 — minimal grid, not a DAM**                                                                      |

Design direction: the admin should read as one cohesive, packaged product — the calm, structured, low-noise interaction quality of Statamic is the right reference point, without borrowing its visual identity. This is only achievable if the admin ships its own compiled CSS/JS (§16) rather than depending on whatever the host's Tailwind setup happens to produce; a page builder that looks half-styled immediately after install undermines the entire "complete CMS out of the box" promise.

---

## 7. Page editor redesign

The current `PageEditor` (`src/Livewire/PageEditor.php`) already blends six responsibilities: section CRUD, drag-order persistence, prop filtering/validation, image upload, publish, and restore. It has not yet become unmanageable, but every feature this section adds (hierarchy, layout/template pickers, SEO, categorized section picker, duplicate/delete, responsive/full-page preview, unpublished-change indicator, validation errors, broken-section warnings) would push it there if bolted on in place. Decompose now, before adding those features, not after.

**Proposed shape** — one thin orchestrator plus five focused children, communicating via Livewire's own `$this->dispatch()` rather than deep prop drilling:

- **`PageEditor`** (orchestrator) — loads the `Page`, holds which section is selected, renders the children below, owns top-level actions: `publish()`, preview-mode toggle. Thin by design.
- **`SectionList`** — everything the current component's left column does today (`updateOrder`, `addSection`, `selectSection`) plus new actions: `removeSection`, `duplicateSection`. The "Add section" picker evolves from a flat button list into a searchable, category-grouped picker, using the metadata from §9's registration API.
- **`SectionFieldPanel`** — renders the schema-driven form for the selected section. Persistence delegates to a `PersistSectionProps` action wrapping the existing (relocated, see §18) per-field-type validation logic. This is also where per-field validation errors become visible to the editor for the first time — today there is none beyond the image MIME check.
- **`PreviewPane`** — presentational only; receives already-rendered HTML or a preview URL. Adds a responsive-width toggle (desktop/tablet/mobile) and a link to the full-page preview route (§5).
- **`RevisionHistory`** — extracted from today's `@if ($showingHistory)` block; gains real pagination (fixing the unbounded `$this->page->revisions()->get()` call flagged as a known gap).
- **`PageSettingsPanel`** — entirely new: title, slug, status, parent, layout/template selection (once those registries exist), SEO fields.

Supporting non-Livewire pieces: `PersistSectionProps` (action wrapping the schema-driven filter/validate logic, now sourced from `FieldTypeDefinition::validate()` per §18), `MediaUploader` (service, §14), and the existing `PublishPage`/`RestorePageRevision` actions, unchanged.

Feature-to-owner mapping:

| Requirement                                     | Lives in                                                                                                                                                                                                                                  |
| ----------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Title, slug, status, hierarchy, layout/template | `PageSettingsPanel` (new)                                                                                                                                                                                                                 |
| SEO                                             | `PageSettingsPanel` (new, a tab)                                                                                                                                                                                                          |
| Section library / categories                    | `SectionList`'s picker (evolved)                                                                                                                                                                                                          |
| Drag-and-drop                                   | `SectionList` (retained behavior)                                                                                                                                                                                                         |
| Delete / duplicate sections                     | `SectionList` (new)                                                                                                                                                                                                                       |
| Responsive preview                              | `PreviewPane` (new)                                                                                                                                                                                                                       |
| Full-page preview                               | `PreviewPane` + route from §5                                                                                                                                                                                                             |
| Unpublished-change indicator                    | `PageEditor` orchestrator — a cheap comparison (timestamp or hash) between `page_sections` state and the latest `page_revisions.snapshot`, not a deep diff                                                                                |
| Autosave                                        | Already exists in effect (every field edit persists immediately) — this slice makes it _visible_ with a "Saved" indicator, not a new mechanism                                                                                            |
| Explicit publishing                             | `PageEditor` orchestrator (retained)                                                                                                                                                                                                      |
| Revisions                                       | `RevisionHistory` (extracted)                                                                                                                                                                                                             |
| Validation errors                               | `SectionFieldPanel` (new)                                                                                                                                                                                                                 |
| Broken-section warnings                         | `SectionList`/`SectionFieldPanel` — today `SectionRenderer`'s catch-and-log is silent to the editor; the renderer needs to also surface which section ids failed when called from an admin context (§11), and this is where that surfaces |

---

## 8. Sections, modules, layouts, templates

- **Section** — presentational, no change from today: a `Illuminate\View\Component` implementing `Sectionable`. Required immediately; already exists.
- **Module** — application-aware. Architecturally identical to a Section (still just an `Illuminate\View\Component`, still rendered through the same `SectionRenderer`/driver pipeline) — the difference is organizational, not technical: a separate registration call (`PageBuilder::module()`), a separate namespace convention, and a distinct grouping/icon in the admin's "Add" picker. Recommendation: `Moduleable extends Sectionable` with no additional required method, so the rendering pipeline never needs to know or care which one it's rendering. **Not required for Milestone 1** — nothing stops a Section today from containing arbitrary Eloquent/service logic in its own `render()`/`with()`; document that informally so early adopters aren't blocked, and add the formal interface/registry ceremony in Milestone 3 once there's a real audience distinguishing the two in practice.
- **Layout** — the outer shell (header/nav/footer/global assets), wrapping rendered section output rather than composing alongside it. Needed in _some_ form for Milestone 1 (something must wrap the page), but starts as one bundled, publishable default layout view rather than a pluggable registry. The formal `Layoutable` contract and `PageBuilder::layout()` registration are Milestone 2/3 work, once more than one layout is a demonstrated need rather than a hypothetical one.
- **Template** — not a renderable component at all. A plain PHP class (`Templateable`) that constrains a page: `defaultLayout()`, `allowedSections()`, `defaultSections()`, `lockedSections()`. Fully deferred to Milestone 2/3 (see §4).

---

## 9. Developer API

```php
PageBuilder::section(HeroSplit::class);
PageBuilder::module(ProductGrid::class);       // Milestone 3
PageBuilder::layout(MarketingLayout::class);   // Milestone 2/3
PageBuilder::template(LandingPageTemplate::class); // Milestone 2/3
```

**Where metadata lives:** static methods on the component class, exactly following the existing, proven `schema(): Schema` convention — not PHP attributes, not a separate registration-builder DSL. This keeps everything IDE-navigable, testable without reflection, and consistent with the one pattern this codebase has already validated.

The required interface stays minimal — unchanged from today:

```php
interface Sectionable
{
    public static function schema(): Schema;
}
```

Richer metadata (icon, category, description, preview image) is an **optional** second interface, so existing and simple sections (including the four test fixtures already in this repo) never need to implement it:

```php
interface HasComponentMeta
{
    public static function meta(): ComponentMeta; // handle, name, description, icon, category, previewImage
}
```

`ComponentMeta` is a small readonly DTO. If a registered class doesn't implement `HasComponentMeta`, the registry derives sane defaults (label from the class basename, category `"General"`, no icon). This directly satisfies "keep ordinary Laravel class components usable where possible" — a one-method section from the current test suite continues to work unmodified.

---

## 10. Component discovery and scaffolding

```bash
php artisan make:page-section Hero
php artisan make:page-module ProductGrid
php artisan make:page-layout Marketing
php artisan make:page-template LandingPage
```

Generated, by default, under `app/PageBuilder/{Sections,Modules,Layouts,Templates}` with matching views under `resources/views/page-builder/{sections,modules,layouts}` — both roots configurable. A section stub extends `Illuminate\View\Component implements Sectionable` with a starter `schema()`; a layout stub includes a `{{ $slot }}`; a template stub is a plain class with no view.

**Discovery vs. explicit registration — recommendation: both, cache-backed.** Discovery scans the configured directories for classes implementing the relevant interface, but the result is written to a cached manifest (`php artisan page-builder:cache`, hooked into `php artisan optimize`) rather than reflected over on every request. In `APP_ENV=production` with no manifest present, discovery **fails loudly** rather than silently falling back to a live filesystem scan — the same discipline Laravel already applies to its own config/route caching, and the only choice that gives predictable production performance. Explicit `PageBuilder::section()` calls in a service provider are unioned with whatever discovery finds, which is what makes registering a package-shipped or vendor-namespaced section (living outside the host's scanned directories) possible without changing the discovery convention.

---

## 11. Rendering architecture

Today, `SectionRenderer` does one thing directly: `new $componentClass(...$props)` followed by `render()->with($data)->render()`, concatenated into an HTML string. That is Blade-only by construction, not by a documented boundary.

**Proposed seam — a driver contract, one implementation:**

```php
interface RendererDriver
{
    public function renderSection(string $componentClass, array $props): string;
    public function supports(string $componentClass): bool;
}
```

`BladeRendererDriver` is today's exact logic, extracted verbatim (Migration Stage D, §20) — no behavior change. A `RendererManager` picks a driver; for now it always picks Blade, because Blade is the only driver that exists. The value of this step now is entirely that the seam exists, not that a second driver ships.

**Why Livewire sections and Inertia are not claimed as supported, and what a real path looks like:**

- _Livewire sections_: a `Livewire\Component` doesn't share `Illuminate\View\Component`'s API and needs a real mount/hydrate lifecycle (`wire:id`, Alpine wiring) that "instantiate, call render, concatenate the string" cannot provide. Livewire components nest correctly inside a **compiled Blade view** via the normal `<livewire:...>` tag — but `RenderPage` today produces a pre-rendered `Htmlable` string, not a compiled view with real Blade directives resolved at request time. A credible `LivewireRendererDriver` requires the _entire public rendering pipeline_ to change from "string concatenation" to "a real Blade view rendered with slots," which is a genuine architectural change, not just a new driver class. This is Milestone 4 work, not a config flag.
- _Inertia_: architecturally the harder case. Inertia's model is one route returning one component-plus-props response for a whole page — not a server-side concatenation of independently rendered fragments. A credible adapter means the public rendering path returns `Inertia::render('PageBuilder/Page', $props)` with an ordered section/props tree, and the _actual pixels_ are drawn by Vue/React components the **host application writes and ships**, one per registered PHP section — the package cannot supply a JS framework and build tool it doesn't want to force on Blade-only consumers. This needs its own dedicated design spec before any code is written, and is explicitly out of scope to design further here.

Both are named directly in the "what not to build yet" section below. Neither is claimed as working until it demonstrably is.

**How the pieces fit together**, preserved from today's proven design and extended:

- _Page resolution_: site (via `SiteResolver`) + stored `path` → single indexed `Page` lookup (§5).
- _Layout applied_: the resolved layout component wraps `SectionRenderer`'s output as its slot.
- _Section prop validation_: each `FieldTypeDefinition::validate()` (§18), replacing today's `filterPropsAgainstSchema()`'s inline per-type logic — same behavior, now per-type and extensible.
- _Dynamic module data_: resolved inside the module's own constructor/`with()`, exactly like a section — no separate pipeline invented for it.
- _Assets_: sections' own CSS/JS remain entirely the host's build concern (Vite, Tailwind, whatever they already run); the package's own admin assets are separate and never mixed with the host's build (§16).
- _Preview vs. published_: identical resolution and driver, different data source (`page_sections` vs. latest `page_revisions.snapshot`) — today's exact, already-tested pattern, unchanged.
- _Failure handling_: for visitors, unchanged — catch, log, skip, never fatal the page. For editors, the same renderer call, invoked with an "admin context" flag, additionally returns which section ids failed and why, so `SectionFieldPanel`/`SectionList` (§7) can surface a warning instead of a silent gap. One method, two calling conventions — not two renderers.

---

## 12. Settings architecture

**Deployment-level** (published `config/page-builder.php`, evolving today's `config/blade-page-builder.php`): `admin.prefix`, `admin.middleware`, `disks.uploads`, `component_paths` (discovery directories), `revisions.retention` (`null` = unlimited, or an integer/duration), `assets.strategy` (default: package-published, no alternative shipped yet).

**Database-level** (the `site_settings` row from §4): `name`, `homepage_page_id`, `default_meta_title`, `default_meta_description`, `logo_media_id`, `favicon_media_id`, `default_social_image_media_id`, `robots_index`, `head_scripts`, `body_scripts`.

**Type-safe API, not string keys:**

```php
$settings = PageBuilder::settings(); // SiteSettings value object
$settings->name;
$settings->homepage();   // resolves the Page
```

Backed by an Eloquent model internally, but exposed to host code as a typed object with named properties/methods — never `Setting::get('arbitrary.key')`. A new setting requires a migration and a new typed property, on purpose: the settings surface stays closed and known, not infinitely extensible via arbitrary keys, which is precisely what keeps it out of "generic app settings store" territory (§3).

---

## 13. Navigation

Two tables (§4): `navigations` (named trees — `main`, `footer`, `legal`, scoped by site) and `navigation_items` (self-referential nesting, either a `page_id` or a custom `url`, `label`, `target`, `order`, `visible`).

```php
$nav = PageBuilder::navigation('main'); // ordered tree of resolved items
```

Each item's effective URL is computed **at render time** from its linked page's current `path`, not stored statically — so renaming or moving a page automatically updates every navigation item pointing at it, with no separate sync step. If a linked page is deleted, `navigation_items.page_id` is set null (`nullOnDelete`) and the admin flags the item as a broken link rather than the request failing.

Rendering stays host-owned by default, per §3: the package hands back the resolved tree; the host's own Blade layout iterates it however it wants. An optional `<x-page-builder::navigation handle="main" />` convenience component exists for the common case but is not the primary contract, and a host is free to ignore it entirely.

---

## 14. Media and assets

**Smallest architecture that will not be a dead end:** one `media` table (§4) — disk, path, filename, mime type, size, alt text, uploader — plus a simple grid picker modal (upload, search, select) wired into the Image field type. No folders, no collections, no tagging, no image-transformation-variant table, no external-media embedding.

This is a deliberate floor, not a ceiling: today's Image field stores a bare path string with zero record-keeping (no alt text, no orphan tracking, no reuse). Introducing one real `media` table now fixes that without committing to a digital-asset-manager's worth of schema the product doesn't need yet. Deletion is best-effort (delete the DB row, attempt to delete the underlying file, log on failure); a "find and clean up unused media" tool is explicitly deferred (Milestone 3+, only if real usage demands it).

---

## 15. Installation system

`php artisan page-builder:install` performs only genuinely safe, additive operations, and **documents** everything that would otherwise mean editing a file the host application already owns:

**Automated:**

- Publish `config/page-builder.php`.
- Publish compiled admin assets (`vendor:publish --tag=page-builder-assets`) to `public/vendor/page-builder`.
- Interactively (with `--no-interaction`-friendly flags for CI) ask: admin path prefix (default `admin/pages`), which middleware group to protect the admin with (default `web`, offered the option to add `auth`), upload disk (default `public`).
- Create the initial `Site` row (a pure insert into a package-owned table — safe).
- Optionally, on request, create a first "Home" page.

**Deliberately not automated — printed as explicit next steps instead:**

- Editing `routes/web.php` to add `PageBuilder::routes();` — the exact snippet and where to put it is printed, never written by the installer.
- Migrations — the installer does not call `Artisan::call('migrate')` on the host's behalf (surprising inside some deploy pipelines); it prints the reminder to run `php artisan migrate`.
- Any Tailwind integration step — none is needed, because the admin ships fully self-styled (§16); the installer says so explicitly rather than attempting to patch `tailwind.config.js`.
- User/permission setup — the installer creates no users and assumes no specific auth package; it prints a short note pointing at `page-builder.admin.middleware` in the published config.

**Final output:** a plain, itemized "what happened / what to do next" list in the terminal.

Migrations themselves stay **unpublished and auto-loaded** via `loadMigrationsFrom()`, exactly as today (`BladePageBuilderServiceProvider::boot()`) — this is simpler to upgrade across versions than published, host-owned migration files, and nothing in the target product requires the host to edit them.

---

## 16. Package assets

Replace the CDN-loaded SortableJS (`resources/views/livewire/page-editor.blade.php`'s `<script src="https://cdn.jsdelivr.net/...">`) with a small, versioned bundle **built by the package itself**, at release time, using its own tiny build step inside this repo — not the host application's build. SortableJS becomes an npm dependency of that internal build and ships compiled inside the package's own `app.js`; there is no external `<script src>` tag left anywhere.

The compiled `app.css`/`app.js` are published via the standard `vendor:publish` mechanism into `public/vendor/page-builder/` (the same idiom Filament, Nova, and Livewire itself already use) and referenced with `asset('vendor/page-builder/app.js')` — self-hosted, works offline, works under a strict CSP (optionally supporting a configured nonce), and is pinned to the exact version of the package installed rather than to a CDN URL string.

The admin's CSS is a small, package-owned compiled Tailwind build, **not** scanned from the host application's Tailwind `content` globs. This is what makes "the administration UI should look correct immediately after installation" actually true regardless of whether the host even uses Tailwind — and it is why §15's installer needs no Tailwind integration step at all.

---

## 17. Authentication and authorization

The package assumes no specific user model or auth package, but needs a credible layer beyond "any authenticated request may do anything."

- **Middleware**: `config('page-builder.admin.middleware')`, host-controlled, default `['web']`.
- **Abilities as Gates the package checks but the host defines**: `page-builder.pages.edit`, `page-builder.pages.publish`, `page-builder.settings.manage`, `page-builder.navigation.manage`. The package ships **permissive fallback behavior** (`Gate::before()` returning `true` when a host hasn't defined a given gate), so a host that configures nothing gets a fully working admin — matching today's "auth left entirely to the host" spirit — while a host that wants a real editor/publisher split can define any of these named gates without touching package code.
- **Publish vs. edit as separate abilities**: an "editor" role can draft; only a "publisher" role can call `PublishPage`. Checked at the call sites in the decomposed editor (§7).
- **Revision attribution without assuming `users.id`**: keep `created_by` as a bare, nullable integer (as today), but stop hardcoding `auth()->id()` inline at each call site (as `PageEditor::publish()` does today) in favour of a single configurable resolver: `config('page-builder.current_user_id_resolver', fn () => auth()->id())`. Apps with multi-guard or unusual auth setups supply their own resolver once, rather than the package assuming `auth()->id()` is always meaningful.

---

## 18. Extensibility

**The single most important change in this plan** is replacing the closed `FieldType` enum with a registry, because it is the one place the current design actively prevents a host from doing something the target product explicitly promises ("developers define the capabilities").

```php
interface FieldTypeDefinition
{
    public function view(): string;                              // Blade partial or component name
    public function validate(mixed $value, Field $field): mixed;  // coercion + whitelisting, per type
    public function default(): mixed;
}

PageBuilder::fieldType('color', ColorFieldType::class);
```

The 8 existing types (Text, Textarea, Richtext, Image, Select, Link, Repeater, Group) ship as the package's own built-in `FieldTypeDefinition` implementations, registered by default — this is where today's `filterScalarValue()`/`filterLinkValue()`/`isAllowedUrlScheme()` logic (`src/Livewire/PageEditor.php`) relocates to, one method per type, rather than one large conditional. `Schema::make()->text()/->select()/...` fluent methods are unchanged; a generic escape hatch (`Schema::field('color', 'brand_colour', label: 'Brand colour')`) lets a host use a registered custom type. `Field`'s public shape (`name`, `type`, `label`, `options`, `itemSchema`) is preserved — `type` becomes a resolvable handle string backed by the registry instead of a closed enum case.

**Other deliberate extension points**, kept restrained rather than making every internal class replaceable:

- **Custom page models**: resolved via the container or a configurable class in config (`config('page-builder.models.page')`), the same pattern Cashier/Sanctum use, so a host needing extra columns/behaviour extends rather than forks.
- **Component metadata**: `HasComponentMeta` (§9).
- **Renderer drivers**: `RendererDriver` (§11).
- **Page/site resolution**: `SiteResolver` (§5).
- **Publishing hooks**: events (§19) plus the Actions remain directly container-bindable if a host needs to wrap them entirely.
- **Asset handling**: `MediaUploader` bound as an interface, swappable.

**Explicitly not part of the extension surface for now**: a plugin system for arbitrary third-party admin pages, and a "register your own settings page" mechanism. Both are real platform-lock-in-shaped ambitions that don't have a forcing use case yet — noted as deferred, not promised.

Internal classes not named above (the registry's internals, the decomposed editor components' internals) are not a supported extension surface and may change between minor versions.

---

## 19. Events and lifecycle hooks

Assessed against the full suggested list:

| Event                                  | Verdict                                      | Why                                                                                                                                                                                                                                                            |
| -------------------------------------- | -------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `PagePublishing` (cancellable)         | **Ship**                                     | Publish is infrequent and high-value (deploy webhooks, cache-busting, a Slack notification, a governance rule like "no publishing after 5pm Friday"). Cancellable, so a host can block it without overriding `PublishPage`.                                    |
| `PagePublished`                        | **Ship**                                     | Same reasoning, after the fact — the natural place for "notify" side effects.                                                                                                                                                                                  |
| `RevisionRestored`                     | **Ship**                                     | Infrequent, audit-worthy.                                                                                                                                                                                                                                      |
| `PageCreating`                         | **Skip**                                     | No plausible reason to cancel page creation pre-emptively; a host needing to intercept creation is better served by overriding the (future) `CreatePage` action in the container than by a passive event.                                                      |
| `PageCreated`                          | **Keep as a candidate, not committed**       | Plausible (auto-seed default sections, notify), but not load-bearing for Milestone 1/2 — revisit if a real use case appears.                                                                                                                                   |
| `PageSaving` / `PageSaved`             | **Skip**                                     | Would fire on every debounced field edit — a firehose, not a meaningful moment. Eloquent's own `saving`/`saved` model events already exist on `PageSection` if a host truly needs this; the package should not duplicate framework events with wrapper events. |
| `PageUnpublishing` / `PageUnpublished` | **Skip until "unpublish" is a real feature** | Today, once published, a page is only ever superseded by a newer revision — there is no explicit unpublish action to hang these events off. Add them only alongside that action, if it's built (a Milestone 2 candidate).                                      |
| `SectionAdded` / `SectionRemoved`      | **Skip for now**                             | Plausible for module-provisioning side effects, but speculative at this stage (YAGNI) and cheap to add later — it's an `Event::dispatch()` call at an existing call site, not a schema change.                                                                 |

General rule applied above: prefer a **cancellable event** when the need is to gate or react to something that already happened or is about to happen (publish, restore), and prefer **letting a host override the action itself** (container rebinding) when the need is to change _how_ an operation is carried out (creation, saving).

---

## 20. Migration from the current code

A staged path, each stage independently shippable and test-covered, with no period where the suite is red across a commit boundary.

**Stage A — FieldType registry.** Objective: replace the closed enum with the registry from §18, zero behavior change for the 8 existing types. Files: `src/Schema/FieldType.php` → new `src/FieldTypes/{FieldTypeDefinition.php,FieldTypeRegistry.php,Types/*.php}`. Migrations: none. Compatibility: `Schema::text()`/`select()`/etc. keep identical signatures; watch the two call sites in `src/Livewire/PageEditor.php` that compare against `FieldType::Repeater`/`FieldType::Group` directly (`addSection()`, `filterPropsAgainstSchema()`) — these need to become registry lookups. Existing tests that must keep passing unmodified in behavior: `tests/Unit/SchemaTest.php`, `tests/Feature/FieldPartialsTest.php`, `tests/Feature/PropTamperingRegressionTest.php`. New tests: a `FieldTypeRegistryTest`, and one regression test registering and using a genuinely custom field type end-to-end. Definition of done: full suite green, plus a passing test proving a host-registered field type works without modifying the package.

**Stage B — Sites and page hierarchy.** Objective: add `sites`, and `pages.site_id`/`parent_id`/`path`/`layout`/`template` without breaking existing `Page`/`PageSection`/`PageRevision` behavior. Migrations: `create_sites_table`, `add_site_and_hierarchy_columns_to_pages`, plus a backfill migration creating one default `Site` and attaching every existing `Page` to it. Compatibility: `tests/TestCase.php`'s `defineDatabaseMigrations()` and any page-creating test helper need a `Site` fixture. New tests: hierarchy creation, `path` computation/uniqueness scoped to a site, homepage resolution. Definition of done: existing model/action tests pass with a Site fixture added; new hierarchy tests pass.

**Stage C — Component registry facade.** Objective: introduce `PageBuilder::section()` and a `PageBuilder` facade without removing the existing `config('blade-page-builder.sections')` array path — union both sources. Files: new `src/PageBuilder.php` (facade/manager); `SectionRegistry` evolves internally but keeps its exact public `resolve()`/`slugs()` shape, since `tests/Unit/SectionRegistryTest.php` and other code already depend on it. Definition of done: both registration styles work side by side; existing registry test passes unmodified; a new facade-registration test passes.

**Stage D — Renderer driver seam.** Objective: extract `SectionRenderer`'s current logic into `BladeRendererDriver` behind `RendererDriver`, with `SectionRenderer` becoming a thin manager that delegates to it — a pure refactor, zero behavior change. Compatibility: `tests/Feature/SectionRendererTest.php` and `tests/Feature/RenderPageComponentTest.php` must pass completely unmodified — write them as characterization tests first if any ambiguity exists about current behavior, then refactor underneath them. Definition of done: identical test results before and after; `RendererDriver` exists and is documented as the seam Milestone 4 will use.

**Stage E — Admin route/asset scaffold and `page-builder:install`.** Objective: package-owned admin route group, compiled asset publishing, install command — deliberately _without_ yet decomposing the editor UI. Mounting the still-monolithic `PageEditor` behind the new admin route first, and decomposing it second, separates two genuinely independent risks. Compatibility: `tests/Feature/PageEditorMountPointTest.php` (which asserts the raw `<livewire:page-builder::page-editor>` tag renders) should keep passing unmodified, since the underlying component still exists and is simply now also reachable through a package route. New tests: admin route registration, an Artisan-test-harness test for the install command. Definition of done: `page-builder:install` runs cleanly under Testbench; the admin route renders the (still monolithic) editor behind configured middleware.

**Stage F — Editor decomposition.** Objective: split `PageEditor` into the components from §7, one extraction at a time — starting with `RevisionHistory` since it is already the most self-contained block in the current view (`@if ($showingHistory)`) — migrating `tests/Feature/PageEditorHistoryTest.php`, `PageEditorLayoutTest.php`, `PageEditorTest.php`, `RepeaterAndGroupFieldsTest.php`, and `PropTamperingRegressionTest.php` alongside each single extraction, never all at once. Definition of done, per sub-step: suite green after each individual component's extraction — the editor is never in a broken state between commits.

Domain additions beyond the current code's scope (navigation, richer settings, media, layouts-as-a-registry, templates, modules) are Milestone 2/3 work and are covered at milestone grain in §21 rather than stage-by-stage here, since this section is specifically about migrating what already exists.

---

## 21. Release roadmap

### Milestone 0 — Architecture correction

**Included:** Stages A–D above (FieldType registry, sites/hierarchy schema, component registry facade, renderer driver seam).
**Excluded:** any new admin screens; no navigation/media/settings tables yet.
**Dependencies:** none — this is the foundation layer everything else sits on.
**Acceptance:** full test suite green (existing 32 plus new coverage); a host can register a custom field type; a page can belong to a site and a parent with a computed `path`; `SectionRenderer` behavior is provably unchanged via characterization tests.

### Milestone 1 — Installable CMS foundation

**Included:** Stage E (admin routes, install command), page index/creation/settings screens, public routing (homepage + nested paths), package-compiled assets (CDN SortableJS retired), the minimal `media` table and image picker, minimal `site_settings` (name, homepage, default meta), Gate-based auth hooks with permissive defaults.
**Excluded:** navigation, formal layout/template registries (one bundled default layout instead), autosave-indicator polish, signed external preview links.
**Dependencies:** Milestone 0 complete.
**Acceptance:** `composer require` → `page-builder:install` → `migrate` → register a section → create and publish a page → view it live, entirely through package-owned screens, in roughly the target ten minutes — zero host-built admin screens required.

### Milestone 2 — Complete visual page-building workflow

**Included:** Stage F (editor decomposition) with drag/duplicate/delete, broken-section warnings, the unpublished-change indicator, responsive and full-page preview, visible validation errors; the navigation system end to end; fuller settings (logo/favicon/social image, now that `media` exists); paginated revision history; layouts formalized as a registry; signed preview links; publish/edit Gate separation.
**Excluded:** templates, modules as a distinct concept, scaffolding commands, any field type beyond Milestone 0's registry contents.
**Dependencies:** Milestone 1.
**Acceptance:** the editor satisfies every item in §7's checklist; no single Livewire component holds more than one clear responsibility.

### Milestone 3 — Ecosystem and extensibility

**Included:** `make:page-section/module/layout/template` generators plus discovery/caching (§10); formal Modules and Templates (§8); richer `HasComponentMeta` (categories, search in the picker); shipping `PagePublishing`/`PagePublished`/`RevisionRestored` (and others only if real demand appears); swappable models via config; documentation aimed at third-party consumers, not just this repository's own author.
**Excluded:** a second renderer driver; global sections/reusable patterns (still deferred, revisit only on demonstrated demand); a full DAM.
**Dependencies:** Milestone 2.
**Acceptance:** a developer outside this project could plausibly install and extend the package using only published documentation, with no need to read the package's own source.

### Milestone 4 — Additional frontend renderers

**Included:** Livewire section lifecycle support (requires moving the public rendering pipeline from a concatenated HTML string to a compiled Blade view with real `<livewire:>` tags — a genuine architectural change, flagged in §11, not a config flag) and a credible Inertia/Vue or Inertia/React adapter.
**Excluded from this plan entirely** beyond identifying the seam — no internal task breakdown is estimated here; the Inertia work in particular needs its own dedicated spec before any code is written.
**Dependencies:** Milestone 3, plus a future dedicated design spec for the Inertia adapter specifically.

---

## 22. First implementation slice

Recommendation, based on inspecting the actual repository rather than the milestone framing alone: the first slice is Milestone 0 in full, plus the earliest sliver of Milestone 1 that makes the foundation work _visible_ rather than purely internal plumbing. Doing only Milestone 0 first would produce several commits of real architectural value with nothing a stakeholder could look at; adding the page index/creation/settings screens and public routing on top gives an end-to-end demo without attempting navigation, media, or the editor decomposition prematurely.

**Sequence:**

1. **FieldType registry** (Stage A) + tests — the riskiest "replace" in this plan, done first while the codebase is smallest and easiest to characterize correctly.
2. **Sites and page hierarchy schema** (Stage B) + tests — everything from step 5 onward (admin routes, public routing) depends on a page having a resolvable site and path.
3. **Component registry facade** (Stage C) + tests — needed before "register a section via `PageBuilder::section()`" can be demonstrated at all.
4. **Renderer driver seam** (Stage D) + tests — low-risk and cheap while already touching adjacent code; unblocks Milestone 4 later without any behavior change now.
5. **Admin route group and `page-builder:install`** (Stage E, routes and install command only — asset compilation is not on the critical path for this slice and can stay one slice behind) + tests.
6. **Page index screen** (list pages, create button) + tests — the first genuinely new, visible admin screen.
7. **Page creation and page settings screen** (title, slug, parent, homepage toggle, meta fields) + tests — this specifically retires the "no page-metadata editing UI" gap that the earlier due-diligence pass on this codebase identified as a real, concrete hole.
8. **Public routing** (homepage and nested-path resolution via the new `path` column) + tests — closes the loop: install, create a page through the new UI, see it live at its resolved path.

**Explicitly out of scope for this first slice**, to be picked up immediately after: package asset compilation (CDN SortableJS survives one more slice — it is not blocking anything above), the `media` table and picker, navigation, editor decomposition (the existing monolithic `PageEditor` is reused as-is behind the new admin route), settings beyond bare name/homepage, and auth Gates beyond middleware configuration.

---

## Recommended destination

This repository becomes an open-source Laravel package that a developer installs to get a complete, self-contained, database-backed visual page-building CMS layered onto their existing application — owning its own admin interface, routing, publishing, and revisions — while every unit of rendered content stays a real PHP/Blade component the developer wrote and controls. Never generated markup stored as canonical content, and never a second frontend framework the developer didn't choose to adopt.

## What not to build yet

- Multi-site/multi-tenant admin UI (the `sites` table is a schema seam only, not a feature)
- Custom domains, or any hosted/SaaS product
- Billing or tenancy UI of any kind
- Global/reusable sections or a "patterns" library
- A full digital asset manager — folders, tagging, transformation variants, external media
- Templates as a visually-buildable thing (templates stay code-defined, by developers)
- Livewire-section or Inertia rendering support (the seam is designed for; the drivers are not built)
- A generic, arbitrary-key application settings store beyond the fixed, typed CMS settings
- PHP-attribute-based component registration (the static-method convention already works and is proven)
- A plugin system for arbitrary third-party admin pages
- Any installer behavior that edits `routes/web.php`, a service provider, or the host's Tailwind config

## Decisions requiring your sign-off

These are the choices in this plan with the largest downstream effect — worth an explicit yes/no before Milestone 0 starts, in the same spirit as the "Decision (human sign-off)" already recorded in the original design spec's amendment.

1. **Introduce the `sites` table now**, single default row, no multi-site UI. Recommended: yes.
2. **Replace the `FieldType` enum with a registry/contract.** This is the largest single break from "evolve" to "replace" anywhere in this plan. Recommended: yes.
3. **Rename the package** from `wearepixel/blade-page-builder` / `WearePixel\BladePageBuilder` to `wearepixel/page-builder` / `WearePixel\PageBuilder`, matching every example command in the target brief. Recommended: yes, done once, early, while there are zero real installs to break.
4. **Ship the admin with its own compiled Tailwind/JS build** rather than relying on the host's Tailwind scanner. Recommended: yes — but note this adds a small internal frontend build/maintenance surface to the package itself that doesn't exist today.
5. **Defer Modules, Layouts-as-a-registry, and Templates** to Milestones 2/3, shipping Milestone 1 with plain Sections and one bundled default layout. Recommended: yes — but confirm this doesn't undercut early adopters who need more than one layout on day one; if that's a real near-term need, the layout registry should move earlier.
6. **Confirm the sequencing in §22** — foundation-first (FieldType, sites, registry, renderer seam) before the first visible admin screens — rather than building visible admin screens first and retrofitting the domain model underneath them.
