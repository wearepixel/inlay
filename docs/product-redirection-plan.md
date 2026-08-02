# Product Redirection Plan — from page-builder engine to a visual composition layer for Laravel applications

Date: 2026-08-02
Status: Decision-ready — no implementation started
Supersedes nothing: `docs/superpowers/specs/2026-07-10-blade-page-builder-design.md` remains the historical record of why the current foundation exists and is not being deleted or rewritten. This document has been revised twice to reconcile it against `docs/product-vision.md` and `docs/v1-implementation-specification.md` — see the **Reconciliation appendix** near the end for the full history of what changed, when, and why.

## Why this document exists

The current repository is a working, tested, schema-driven page-building **engine**. It is not yet the **product**: an open-source, Laravel-native visual composition layer that a developer installs into an existing custom application — not a CMS the application is rebuilt on top of — so that non-developers can safely compose and configure developer-built Blade components, both public and application-aware, without the developer building an admin shell, routing, or publishing controls themselves.

This document inspects the actual code that exists today, classifies what should survive unchanged, evolve, or be replaced, and lays out a concrete, staged path to the product described in `docs/product-vision.md` and frozen precisely in `docs/v1-implementation-specification.md`. It does not begin implementation. Every recommendation is grounded in specific files and classes in this repository, not generic CMS wisdom.

---

## 1. Current foundation

| Part                                                                                                | File(s)                                          | Classification                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               | Why                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| --------------------------------------------------------------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Page`, `PageSection`, `PageRevision` models                                                        | `src/Models/*.php`                               | **Evolve**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | The shape (final classes, `array` casts on JSON columns, an enum cast for status) is correct and proven by 32 passing tests. They need new columns (site scope, hierarchy, an optional public path, a Page Definition handle) and new relationships, not a rewrite.                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| `pages`, `page_sections`, `page_revisions` tables                                                   | `database/migrations/2025_01_01_*.php`           | **Evolve**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | The three-table split (live draft / append-only publish log) is the right shape for the fuller product too — see §4. They gain columns and siblings (sites, settings, media), they are not replaced.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `PageStatus` enum                                                                                   | `src/Enums/PageStatus.php`                       | **Retain**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | `Draft`/`Published` is sufficient for the target product's v1. No change proposed.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| `Schema` / `Field` fluent builder                                                                   | `src/Schema/Schema.php`, `src/Schema/Field.php`  | **Evolve, rename**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | The builder _pattern_ — a fluent chain, with `mapsToClass()`-style modifiers already attaching to "the last field defined" via `$lastField` tracking — is exactly the right shape to extend, not replace. Renamed to `Settings`/`Setting` (`docs/v1-implementation-specification.md`) to match the corrected public vocabulary, and extended so each field-defining call (`->text('heading')`) returns a chain of further modifier calls (`->label()`, `->required()`, `->maxLength()`, `->visibleWhen()`) rather than one call taking an ever-growing list of named arguments — a natural extension of the `$lastField` mechanism already present, not a new mechanism. `mapsToClass()` specifically is **removed**, not retained — see §9. |
| `FieldType` enum                                                                                    | `src/Schema/FieldType.php`                       | **Evolve** — reversed from an earlier draft of this plan, which classified this as **Replace** in favour of a third-party-extensible registry. **That is reversed.** `FieldType` stays a closed, built-in enum for v1, gaining `Toggle` and `Number` cases (`Select` absorbs "radio/segmented" as a presentation hint, not a new case). A third-party field-type registry is real, plausible future work — deferred post-1.0, not built now, because proving the composition workflow does not require it and building a good, secure public extension point here is real effort with no v1 payoff. See §18. |
| `Sectionable` contract                                                                              | `src/Contracts/Sectionable.php`                  | **Evolve, rename**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | Renamed to `PageBuilderComponent` (`docs/v1-implementation-specification.md`) — direct, searchable, and immediately understandable to a Laravel developer, in place of the more abstract `Composable` an earlier draft of this plan proposed. The required method renames from `schema(): Schema` to `settings(): Settings`, matching the class renames above.                                                                                                                                                                                                                                                                                                                                                                               |
| `SectionRegistry`                                                                                   | `src/Support/SectionRegistry.php`                | **Evolve**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | `resolve()`/`slugs()` and the config-array-backed singleton pattern are correct and already regression-tested (`tests/Unit/SectionRegistryTest.php`). It becomes the internals of a single component registry reachable through a `PageBuilder` facade, but its public method names and behavior (throw `InvalidArgumentException` on an unknown handle) are preserved, not deleted.                                                                                                                                                                                                                                                                                                                                                         |
| `SectionRenderer`                                                                                   | `src/Support/SectionRenderer.php`                | **Evolve**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | The resolve → instantiate → `render()->with($data)->render()` → catch-and-log-per-component logic is correct in its defensive discipline but wrong in its instantiation mechanism — see §11's corrected renderer design. It becomes `BladeComponentRenderer`, rendering each component through a real, compiled Blade view rather than manual reflection-based invocation, preserving the exact catch-and-skip behavior.                                                                                                                                                                                                                                                                                                                     |
| `PublishPage`, `RestorePageRevision` actions                                                        | `src/Actions/*.php`                              | **Evolve**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Transactional, single-purpose, non-destructive restore (`RestorePageRevision` always calls `PublishPage` again rather than rewinding history) is correct and stays. `PublishPage` gains the responsibility of resolving _fully-resolved_ effective settings into the snapshot it writes (§4, `docs/v1-implementation-specification.md`) rather than copying stored values verbatim; event dispatch (§19) is added around the existing `execute()` calls.                                                                                                                                                                                                                                                                                     |
| Draft/publish/revision model                                                                        | (behavior, not one file)                         | **Retain, extend**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | `page_sections` as the one live draft tree, `page_revisions` as an append-only whole-tree snapshot log, is the right foundation for the fuller product. What's added, not changed: drafts store sparse editor overrides rather than every field's value, and revisions store fully-resolved settings (`docs/v1-implementation-specification.md`).                                                                                                                                                                                                                                                                                                                                                                                            |
| `PageEditor` Livewire component                                                                     | `src/Livewire/PageEditor.php`                    | **Replace (as a monolith), retain (as behavior)**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | At ~300 lines it already owns component CRUD, drag-order persistence, prop filtering/validation, image upload, publish, and restore — six responsibilities in one class. §7 decomposes it into an orchestrator plus five focused child components. Every operation it currently performs is preserved; only its shape as a single component changes.                                                                                                                                                                                                                                                                                                                                                                                         |
| `filterPropsAgainstSchema()` / `filterLinkValue()` / `filterScalarValue()` / `isAllowedUrlScheme()` | `src/Livewire/PageEditor.php`                    | **Retain, relocate**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | This is the package's real security boundary (regression-tested in `tests/Feature/PropTamperingRegressionTest.php`) and must not be diluted. Its logic moves onto per-`FieldType` coercion methods, one method per case in the (retained, closed) enum, so each type owns its own coercion rule — but the net behavior, undeclared keys dropped and values coerced to type, is preserved exactly, and now runs unconditionally regardless of a field's current `visibleWhen()` state (`docs/v1-implementation-specification.md`).                                                                                                                                                                                                            |
| Field partials (`resources/views/fields/*.blade.php`)                                               | 8 files                                          | **Evolve**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | The one-partial-per-type, resolved-by-convention pattern is correct and is retained under the closed `FieldType` model — a `Toggle`/`Number` partial is added the same way.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| `page-editor.blade.php` fixed 3-column layout                                                       | `resources/views/livewire/page-editor.blade.php` | **Replace**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | Superseded by the package-owned admin screens in §6/§7. The _editing operations_ it wires up (drag via SortableJS, per-field `wire:model`) are retained; the specific 3-column Blade view is not.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            |
| CDN-loaded SortableJS                                                                               | inline `<script>` in the view above              | **Replace**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | See §16 — this is a supply-chain and offline-reliability liability that must not ship in the target product.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `config/blade-page-builder.php`                                                                     | `config/blade-page-builder.php`                  | **Evolve**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | The `sections` map and `disk` key are retained as concepts and grow into the fuller config surface in §12.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| `BladePageBuilderServiceProvider`                                                                   | `src/BladePageBuilderServiceProvider.php`        | **Evolve**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | Config merge, migration loading, view loading, Blade component registration, and Livewire namespace registration are all correct and stay. It gains conditional admin route registration, asset publishing, and command registration.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| Test suite (32 tests, 108 assertions)                                                               | `tests/**`                                       | **Retain as the regression baseline**                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | Nothing here is discarded. Some tests will need to move or be adapted as naming changes (`Sectionable`→`PageBuilderComponent`, `schema()`→`settings()`) and as the editor is decomposed, but their _assertions about behavior_ are what must keep passing, not their literal current form.                                                                                                                                                                                                                                                                                                                                                                                                                                                   |

**Naming note, carried forward:** the product's public name is still unsettled — `docs/product-vision.md`'s naming section shortlists Fixture, Lattice, and Inlay against the current `wearepixel/blade-page-builder` working name, without committing to any of them. This plan does not recommend a Composer package/namespace rename until that's resolved separately. The `PageBuilder` facade name is a neutral placeholder for "the package's static entry point," independent of the eventual public product name.

---

## 2. Target product experience

The bar: a competent Laravel developer, starting from a normal Laravel app, has a working visual composition layer in about ten minutes.

### Install

```bash
composer require wearepixel/page-builder
php artisan page-builder:install
php artisan migrate
```

### Wire up routing

```php
use WearePixel\PageBuilder\Facades\PageBuilder;

PageBuilder::routes();
```

Registers both the admin route group and the public catch-all (§5). A host that wants only one half calls `PageBuilder::adminRoutes()` or `PageBuilder::publicRoutes()` — and a host composing only application-embedded surfaces may call neither, relying entirely on `PageBuilder::renderPage()` from its own existing routes.

### Register and describe the first component

```php
final class HeroSplit extends \Illuminate\View\Component implements PageBuilderComponent
{
    public function __construct(
        public string $heading = 'Welcome',
        public string $layout = 'image-right',
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()
            ->text('heading')
                ->label('Heading')
            ->select('layout')
                ->label('Layout')
                ->options(['image-left' => 'Image on left', 'image-right' => 'Image on right']);
    }

    public function render(): \Illuminate\View\View
    {
        return view('sections.hero-split');
    }
}
```

```php
PageBuilder::category('Headers')
    ->icon('layout-header')
    ->components([HeroSplit::class]);
```

### Compose and publish

In the admin: **New Page** → **Add component** from the categorized picker → **HeroSplit** → edit its fields with the live preview updating alongside → **Publish**.

### View it live

```
http://app.test/            (resolved as the configured homepage)
http://app.test/about/team  (a nested page, if hierarchy was used)
```

Rendered by the package's public catch-all route using the exact same renderer the admin preview used — no separate production renderer to diverge from the preview.

**The same flow applies beyond a marketing homepage.** A `CollectionPage` composed from a `CampaignHero` and a real, data-backed `ProductCollection` component follows an identical install → register → compose → publish path. The only addition is a **Page Definition** — a developer-authored class constraining what a collection page is allowed, required, and forbidden to contain — chosen once, at creation.

---

## 3. Product boundaries

**The governing boundary: the package owns composition, the application owns presentation.**

**The package owns:** Component registration, categories, schemas and settings, validation, placement rules, Page Definitions, ordering, persistence, drafts, previews, publishing, revisions, permissions, the administration experience.

**The consuming application owns:** Component markup, frontend CSS, responsive behaviour, accessibility, JavaScript behaviour, frontend assets, business logic, domain models, application data, and — critically — the mapping from a structured setting (`alignment = left`) to whatever it actually produces (a Tailwind class, a Bootstrap class, a CSS custom property, a Blade branch, nothing at all).

**This is not a CRUD-administration boundary.** The package never gives an editor arbitrary access to the host's Eloquent models the way Filament or Nova would give a developer's internal team. It gives an editor access to exactly the components and fields a developer chose to expose, arranged inside exactly the constraints a developer defined. Filament and Nova remain entirely separate, coexisting tools for arbitrary internal data administration.

**A registered component's own view is unrestricted host application code.** The package's authority stops at "resolve this component class, pass it these settings and this context, render it through Laravel's real Blade component system." What that component's own `.blade.php` view contains — nested Blade components, `<livewire:contact-form />`, Alpine, AJAX, a Vue or React island, forms posting into the host's own controllers — is entirely the host application's business, and requires no package-level awareness, adapter, or registration of any kind. This is the single most important clarification in this revision of the plan; see §11.

Two places in the "package owns / host owns" split still need pushing back on rather than accepting as given:

- **"Custom middleware and authentication decisions" is not cleanly host-only.** The package must own _how_ configured middleware gets attached to its own route group (package plumbing — see §5), even though _which_ middleware/guard is chosen is entirely the host's call.
- **Settings needs a narrower boundary than "the package owns settings."** The package should own a small, fixed, typed set of CMS-relevant _site_ settings (name, homepage, default meta — see §12), not a general-purpose host application settings store. This is a different "settings" from a Component's own editable settings (§9) — a deliberate, non-colliding vocabulary overlap, since `PageBuilder::settings()` (site-wide config) and `Settings::make()` (a single component's field schema) name genuinely different things at genuinely different scopes.

Navigation deserves one clarification, not a correction: the package owns navigation _structure, storage, and CRUD_, but does **not** own navigation _visual rendering_ by default (§13).

---

## 4. Domain model

### Sites — explicit recommendation

**Introduce a `sites` table now**, even though the first usable release exposes exactly one site and no multi-site UI. Reasoning unchanged: `pages` currently has no tenant/site scope at all, and retrofitting one after real installations exist is a breaking migration for every adopter, while adding it now costs nothing beyond one join and one auto-created default row.

### Proposed tables

| Table                     | Purpose                                  | Significant columns                                                                                                                                                                                                                                                      | Relationships                                                                                                 | v1 or deferred                                                                                                          |
| ------------------------- | ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `sites`                   | Tenant/site scope seam                   | `id`, `name`, `domain` (nullable), `is_default` (bool)                                                                                                                                                                                                                   | `hasMany` pages, one `site_settings` row                                                                      | **Required for v1** (schema only — no multi-site UI)                                                                    |
| `pages` (evolve)          | Canonical composition + editorial record | adds `site_id` FK, `parent_id` FK nullable (self-referential), `path` (**nullable** — a page created purely to be embedded inside a host-owned route has none), `definition` (nullable string handle — the registered Page Definition, §8)                               | `belongsTo` site, `belongsTo` parent (self), `hasMany` children (self), existing `hasMany` sections/revisions | **Required for v1**                                                                                                     |
| `page_sections` (evolve)  | Live draft component tree                | `handle` (registered component handle, renamed from `section_type`), `order`, **`settings`** (renamed from `props` — stores **sparse editor overrides only**, not every field's current value, per `docs/v1-implementation-specification.md`'s effective-settings model) | unchanged                                                                                                     | **Retained, extended**                                                                                                  |
| `page_revisions` (evolve) | Append-only publish log                  | `snapshot` entries carry **fully-resolved** settings for every field (not sparse), plus the originating `page_sections.id` per entry and the component's `settingsVersion()` at publish time                                                                             | unchanged                                                                                                     | **Retained, extended**                                                                                                  |
| `site_settings`           | Typed, per-site CMS settings             | `site_id` FK (unique), `name`, `homepage_page_id` (FK, nullable), `default_meta_title`, `default_meta_description`                                                                                                                                                       | `belongsTo` site, `belongsTo` homepage page                                                                   | Minimal columns **required for v1**                                                                                     |
| `media`                   | Minimal asset record                     | `id`, `disk`, `path`, `filename`, `mime_type`, `size`, `alt_text` (nullable), `uploaded_by` (nullable int, no FK)                                                                                                                                                        | none beyond FKs pointing at it from settings                                                                  | **Required for v1** (minimal — see §14; backs the `ImageValue` value object, `docs/v1-implementation-specification.md`) |

**One relaxation retained from an earlier draft: `pages.path` (and public catch-all routing participation generally) is optional, not assumed.** A page created purely to be embedded inside a host-owned route can have a null `path` and is simply invisible to the public catch-all (§5), rendered only where a host explicitly calls `PageBuilder::renderPage($page)`.

If a genuinely distinct composition root (not a page at all) becomes a real, demonstrated need later, the cleanest path is likely separating "the ordered component tree plus its revisions" from "the page-specific routing and SEO wrapper around it." That is a long-term seam worth keeping in mind, not a v1 table.

### Explicitly assessed and rejected (for now)

- **Page Definitions as database rows** — rejected. A Page Definition constrains _behaviour_ and is a code/policy concern, not editorial content — it renders nothing and is not a Component. It is a plain PHP class (`PageDefinition`, §8) referenced by `pages.definition` as a string handle. No `page_definitions` table.
- **Nested composition, regions, or a "wraps content" component capability** — removed from v1 architecture entirely, not merely deferred. An earlier draft of this plan proposed `AcceptsChildren` and `WrapsContent` as declared component capabilities, anticipating nested composition and editor-selectable page shells. **Both are removed.** V1 composition is a flat, ordered list, full stop, rendered inside whatever Blade layout the host's own route or controller already extends — exactly how `RenderPage` is already meant to be used today. This was already true in practice under the earlier draft (neither capability was ever exercised in v1); this revision removes the speculative interfaces themselves rather than leaving them undocumented and unused.
- **A third-party-extensible `FieldType` registry** — deferred, not built, for v1 (reversed from an earlier draft — see §1, §18).
- **A generic `settings` key/value (EAV) table** — rejected in favour of the structured `site_settings` table above.
- **Full digital-asset-manager tables** — rejected for v1. See §14.
- **A setting-alias mechanism** alongside versioned migration — rejected. See §9; one predictable mechanism (`migrateSettings()`) covers this case, and a second, overlapping one would violate that.

---

## 5. Routing architecture

Unchanged in shape from earlier drafts. Admin routes registered inside the package's own route group (configurable prefix/middleware); a public catch-all matching only pages with a non-null `path`, registered last so host routes always win; `PageBuilder::routes()`/`adminRoutes()`/`publicRoutes()` as the registration API; `PageBuilder::renderPage(Page $page, bool $draft = false, array $context = []): Htmlable` as the primitive both the public controller and the admin preview call internally, also directly usable by a host wanting full control of its own response or embedding a composition inside a route it already owns. Preview routes: in-admin (no separate route, inside the authenticated admin group) and full-page (`page-builder.pages.preview`). Signed external preview links remain Milestone 2, not required for v1's user journey (`docs/v1-implementation-specification.md` §3). Site resolution is delegated to a single swappable `SiteResolver` interface, trivially returning the one default site for now.

---

## 6. Complete administration application

Unchanged from earlier drafts: dashboard (minimal in v1), page index, page creation (with a Page Definition picker), page editor, page settings (including an optional public path field), SEO/social metadata (text fields in v1), general settings, revision history, preview, and a minimal media picker. Design direction: the calm, structured, low-noise interaction quality of Statamic is the right reference point, without borrowing its visual identity — achievable because the admin ships its own compiled CSS/JS (§16).

---

## 7. Page editor redesign

Unchanged in decomposition strategy: one thin `PageEditor` orchestrator plus `ComponentList` (categorized, searchable "Add component" picker, drag reorder, duplicate/delete, disabled controls for a Page-Definition-locked component), `ComponentFieldPanel` (schema-driven form, per-field validation errors, `visibleWhen()`/`hiddenWhen()` as pure display logic), `PreviewPane` (the iframe from §11/`docs/v1-implementation-specification.md`), `RevisionHistory` (paginated), and `PageSettingsPanel` (title, slug, hierarchy, Page Definition selection at creation, SEO). A Page Definition, when one applies, is chosen once at creation; changing it afterward is a deliberately validated action, not a casual toggle.

---

## 8. The Component model and Page Definitions

**Everything registered and rendered by the builder is one concept: a Component.** One base contract, one registration verb, one rendering mechanism.

### The base contract

```php
interface PageBuilderComponent
{
}
```

Renamed from `Composable` to `PageBuilderComponent` — direct, searchable, and immediately understandable, which matters more here than an abstractly elegant name. **It is a marker interface, not a contract with a required method.** `settings(): Settings` (renamed from `schema(): Schema`) remains fully supported and preferred for complex schemas, but is optional — a component may declare its settings via constructor attributes instead, and requiring `settings()` on the interface itself would make it impossible for an attribute-only component to satisfy the contract, since PHP cannot make an interface method simultaneously required and optional. A component's resolved settings are built from whichever of attributes, `settings()`, or both a class actually declares, merged by setting name with `settings()` taking precedence — full detail, including the rule that a constructor parameter is never an editor setting unless explicitly exposed by one of those two mechanisms, is frozen in `docs/v1-implementation-specification.md` §7–§9 and is not restated here to avoid the two documents drifting on the same detail.

### A registered component is always a Blade component

The outer registered unit — the thing `PageBuilder::component()` accepts, the thing a Page Definition's `allowedComponents()` lists — is always a real `Illuminate\View\Component` subclass. There is no second, separately-registrable outer kind, and no renderer choice to make between them. **This is a correction from an earlier draft of this plan**, which proposed registering Livewire components directly as an alternative outer unit, with its own renderer, auto-detection between the two, and a staged Blade-then-Livewire delivery plan. That entire apparatus is removed — see §11 for why it was unnecessary, not merely simplified.

### What a registered component's own view may contain

Unrestricted host application code, including nested Blade components, real Livewire components (`<livewire:contact-form :campaign="$campaign" />`), Alpine, AJAX, ordinary JavaScript, a Vue or React island, forms, domain services, Eloquent models, and any other frontend behaviour the application already supports. None of this requires the package's awareness, registration, or a rendering adapter of any kind — it is handled entirely by the host application's own existing Blade/Livewire/JavaScript setup, exactly as it would be for any other page in that application. See §11.

### Application-aware components

A component that needs live application data (a `ProductGrid`, a `BookingAvailability` panel) is an ordinary component whose own constructor or `with()` resolves that data — from a stored setting (a selected collection ID) or from supplied runtime context (§`docs/v1-implementation-specification.md`'s runtime context model). There is no separate "Module" registration path; this was already true under earlier drafts and remains true here.

### Page Definitions

A **Page Definition** is a developer-authored, non-rendering class declaring what a given kind of page is allowed, required, and forbidden to contain:

```php
abstract class PageDefinition
{
    public function allowedComponents(): ?array { return null; }   // null = unrestricted
    public function requiredComponents(): array { return []; }
    public function lockedComponents(): array { return []; }       // covers both "cannot be removed" and "cannot be reordered"
    public function defaultComponents(): array { return []; }
}
```

It is not a Component, renders nothing, and is referenced by `pages.definition` as a string handle. **Every array here is a literal, explicit list of concrete component classes, written by hand — never a category reference, and never a helper that resolves a category to its members on the developer's behalf.** An earlier draft of this plan proposed a `PageBuilder::category('Headers')->handles()` convenience specifically so a Page Definition could "allow a whole category," resolved once at registration time to avoid silent drift. **That convenience is removed.** Even resolved once, the API shape itself invites a future reader to assume it tracks a category's _current_ membership dynamically — a dangerous misreading for a placement guardrail. A developer who wants "all of Headers" writes out that class list by hand.

### What this replaces

- **Section** — no change in behavior; becomes the default (no capabilities) case of Component.
- **Module** — dropped as a registration-time concept, as before: application-aware behavior is ordinary component code, not a distinct registered kind.
- **Layout / `WrapsContent`** — removed from v1 architecture entirely (§4). The host's own existing Blade layout wraps composed output; the package has no opinion and no interface for this.
- **`AcceptsChildren` / nested composition** — removed from v1 architecture entirely (§4). Composition is a flat ordered list.
- **Template** — remains **Page Definition**, a genuinely separate, non-rendering concept.
- **A directly-registrable Livewire outer component, with its own renderer** — removed entirely (§11), not deferred, not staged. It was never necessary once the outer Blade component's own view is rendered through the real Blade view engine.

---

## 9. Developer authoring model

```php
PageBuilder::component(HeaderClassic::class);

PageBuilder::components([
    HeaderClassic::class,
    HeaderCentered::class,
]);

PageBuilder::category('Headers')
    ->icon('layout-header')
    ->description('Headers and primary page introductions')
    ->components([
        HeaderClassic::class,
        HeaderCentered::class,
    ]);
```

One registration verb for the one renderable concept, one for grouping, one separate verb for Page Definitions (`PageBuilder::pageDefinition()`). No renderer choice anywhere in this API — there is only one renderer (§11).

### The constructor is the source of truth for type and default

```php
final class Hero extends Component implements PageBuilderComponent
{
    public function __construct(
        public string $heading = 'Welcome',
        public bool $showAction = false,
        public ImageSize $imageSize = ImageSize::Large,
        public ?LinkValue $action = null,
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()
            ->text('heading')
                ->label('Heading')
                ->required()
                ->maxLength(100)
            ->toggle('showAction')
                ->label('Show action')
            ->select('imageSize')
                ->label('Image size')
                ->options(ImageSize::class)
            ->link('action')
                ->label('Action')
                ->visibleWhen('showAction', true);
    }

    public function render(): \Illuminate\View\View
    {
        return view('components.hero');
    }
}
```

`Settings::make()` declares _editor-facing_ behaviour only — which field widget, label, help text, validation rules, visibility, and (for `select`) which options. It never redeclares the PHP type, nullability, or default value — those are read once, via reflection, from the constructor's promoted properties, and cached (§`docs/v1-implementation-specification.md`'s manifest/caching design) rather than re-reflected on every request. A `select`'s `options(EnumClass::class)` reads the enum's backed cases directly.

Registration-time failures, not silent resolution: a `settings()` field with no matching constructor parameter, a `select`'s declared enum not matching the constructor parameter's actual type, a required non-nullable constructor parameter with no default and no explicit `settings()`-level default, and a duplicate setting name all throw a clear, developer-facing exception the moment the component is registered — never discovered later at render time.

### PHP attributes as an optional authoring style

```php
final class Hero extends Component implements PageBuilderComponent
{
    public function __construct(
        #[Text(label: 'Heading', maxLength: 80)]
        public string $heading = 'Welcome',

        #[Toggle(label: 'Show action')]
        public bool $showAction = false,
    ) {}

    public function render(): \Illuminate\View\View
    {
        return view('components.hero');
    }
}
```

A component may use attributes only, `settings()` only, or both. When both declare the same setting, the fluent `settings()` declaration wins for that setting's metadata; the constructor's type and default are always authoritative regardless of which authoring style declared the field; a conflicting field _type_ between the two fails at registration, never resolved silently. Attributes use ordinary PHP language attributes, available on the package's existing `^8.2` baseline — this feature does not require PHP 8.4 or property hooks.

### `mapsToClass()` — removed

`Field::$classResolver`, `resolveClass()`, and `Schema::mapsToClass()` are deleted, not retained as optional convenience. A developer maps a setting to presentation with an ordinary method on the component class:

```php
public function alignmentClass(): string
{
    return match ($this->alignment) {
        Alignment::Left => 'text-left',
        Alignment::Centre => 'text-center',
        Alignment::Right => 'text-right',
    };
}
```

This is more idiomatic Laravel, not less, and removes the one place the schema API implied the package had an opinion about CSS-class mapping — directly contradicting "the package owns composition, the application owns presentation" even as opt-in convenience.

### Effective settings: sparse drafts, resolved publishes

A draft `page_sections.settings` row stores only what an editor has explicitly overridden from the component's own current defaults — not a full copy of every field's value. "Effective settings" for rendering is always `current defaults merged with stored overrides`, computed by one uniform algorithm used everywhere (draft render, published render, revision preview, restore). This means a developer can add a brand-new field to an already-in-production component and every existing instance immediately, correctly behaves as if that field were set to its new default — no backfill migration required. **Publishing** takes this one step further: at publish time, the fully-resolved effective settings for every declared field are written into the new `page_revisions.snapshot` entry, not the sparse overrides — so a later change to the component's own constructor default never silently changes how an already-published historical revision renders. Full semantics — nested group/repeater merging, the null-vs-missing distinction, reset-to-default behaviour, enum and value-object serialization, restore-as-explicit-overrides — are frozen in `docs/v1-implementation-specification.md`; this is genuinely load-bearing and is not restated here to avoid two documents drifting on the same detail.

### Component settings versions

A component may declare `public static function settingsVersion(): int` (default `1`), recorded on every stored instance and revision snapshot. A version mismatch at hydration time triggers an in-memory-only `migrateSettings(array $settings, int $fromVersion): array` if the component provides one; if it doesn't, resolution follows the same visitor/draft/publish-blocking distinction specified for an orphaned enum value (`docs/v1-implementation-specification.md` §10) — never a silent rewrite of stored data, and never a database-wide migration framework in v1. No separate setting-alias mechanism exists alongside this — a simple rename is exactly the "structural change" `migrateSettings()` already handles, and supporting two overlapping mechanisms for the same problem was rejected as needlessly ambiguous.

---

## 10. Component discovery and scaffolding

```bash
php artisan make:page-component Hero
php artisan make:page-definition CollectionPage
```

Unchanged from earlier drafts: one generator for the one renderable concept, one for the one non-renderable concept, generated under `app/PageBuilder/{Components,Definitions}` (configurable). Discovery is cache-backed — constructor/attribute reflection happens at registration time in development and is written to a compiled manifest for production use (`php artisan page-builder:cache`, hooked into `php artisan optimize`), never re-reflected per request. In `APP_ENV=production` with no manifest present, discovery fails loudly rather than silently falling back to a live scan. The manifest stores everything needed to render and validate without further reflection: handle, display metadata, category, settings definitions (field types, derived PHP types/defaults, enum classes, value-object classes), and settings version. It never stores executable closures.

---

## 11. Rendering architecture — corrected

**Today's `SectionRenderer` instantiates every component with bare `new $componentClass(...$props)`, then calls `render()->with($data)->render()` manually — this bypasses Laravel's real, compiled view-rendering pipeline rather than using it.** An earlier draft of this plan treated this as requiring, eventually, a second renderer implementation (a `LivewireComponentRenderer`) so a directly-registered Livewire component could be supported, staged behind auto-detection and a Blade-then-Livewire delivery plan. **This entire apparatus is removed, not simplified, because the underlying premise was wrong: the outer registered unit is never a Livewire component in the first place (§8).**

### The v1 rendering path — one renderer

```php
final class BladeComponentRenderer
{
    public function render(string $componentClass, array $settings, string $key): string
    {
        return view('page-builder::render-component', [
            'component' => $componentClass,
            'settings' => $settings,
        ])->render();
    }
}
```

where `resources/views/page-builder/render-component.blade.php` contains, in essence:

```blade
<x-dynamic-component :component="$component" :="$settings" />
```

This renders each component through Laravel's genuine, compiled Blade component system — the same mechanism used for any other Blade component in any other view in the application — not through manual reflection-based construction. Today's per-component try/catch discipline is preserved exactly at the PHP call site around this `view(...)->render()` call: a broken component is caught, logged, and skipped, never fataling the rest of the page.

**Because rendering now goes through Blade's real compiler, a registered component's own view may freely contain `<livewire:contact-form />`, or any other Blade or Livewire directive, with zero special handling.** Blade's compiler resolves that tag normally the moment the component's own `.blade.php` view is compiled and rendered — which happens automatically as part of `<x-dynamic-component>` rendering the outer component. No adapter, no auto-detection, no second renderer implementation is needed for this to work, because it was never a page-builder rendering concern in the first place — it is exactly how a Livewire component nests inside any Blade view in any Laravel application, page-builder or not.

**No `RendererDriver` interface exists in v1.** Building an interface for a single implementation, in anticipation of a hypothetical future outer-adapter (Inertia, Vue), would be exactly the speculative architecture this correction exists to remove. The possibility of a future adapter is preserved simply because nothing here prevents extracting an interface later, when a second implementation is real work worth doing — not because a seam is built and left empty today.

### Inertia, Vue, React

Unaffected by this correction, and still not claimed as supported. Inertia's whole-page component-plus-props response model is a different shape of problem entirely from "render this Blade component," needing its own dedicated design spec before any code is written — nothing about the corrected renderer design above accelerates or blocks that separately-scoped future work.

### How the pieces fit together

- _Page resolution_: for a routable page, site + stored `path` → single indexed lookup (§5). For a host-embedded composition, the host's own route resolves its own model and calls `renderPage()` directly.
- _Settings resolution_: `renderPage($page, context: [...])` computes effective settings (§9) for each component, merges in any matching runtime context, and passes the result as the constructor arguments `<x-dynamic-component>` receives.
- _Preview vs. published_: identical resolution and renderer, different data source and completeness (sparse draft vs. fully-resolved snapshot) — see §9 and `docs/v1-implementation-specification.md`.
- _Failure handling_: unchanged discipline — catch, log, skip, never fatal for visitors; visible, specific warnings for editors, now also covering a `settingsVersion()` mismatch with no migration path.

---

## 12. Settings architecture

Unchanged. **Deployment-level** (published `config/page-builder.php`): `admin.prefix`, `admin.middleware`, `disks.uploads`, `component_paths`, `revisions.retention`, `assets.strategy`. **Database-level** (`site_settings`, §4): `name`, `homepage_page_id`, `default_meta_title`, `default_meta_description`, accessed via a type-safe `PageBuilder::settings(): SiteSettings` value object, never arbitrary string keys. (This is deliberately a different "settings" from a Component's own `Settings::make()` schema, §9 — site-wide configuration versus one component's editable fields, at different scopes, with no actual name collision.)

---

## 13. Navigation

Unchanged. `navigations`/`navigation_items` tables, deferred to Milestone 2; `PageBuilder::navigation('main')` returns a resolved tree computed at render time from each linked page's current `path`; rendering stays host-owned by default.

---

## 14. Media and assets

Unchanged in scope: one minimal `media` table plus a simple grid picker, no folders/collections/tagging/transformation variants. Now explicitly the backing store for the `ImageValue` value object (§`docs/v1-implementation-specification.md`), replacing today's bare path string with a typed, hydrated reference carrying alt text and metadata.

---

## 15. Installation system

Unchanged. `php artisan page-builder:install` performs only genuinely safe, additive operations and documents everything else. Its printed next-steps now also mention configuring `PageBuilder::previewView()` (renamed from an earlier draft's `previewLayout()` — see `docs/v1-implementation-specification.md`) as a required step for accurate preview.

---

## 16. Package assets

Unchanged: a small, versioned, package-built compiled `app.css`/`app.js`, published via `vendor:publish`, self-hosted, CSP-friendly, no CDN, scoped so it never applies to anything rendered inside `BladeComponentRenderer`'s output.

---

## 17. Authentication and authorization

Unchanged: middleware config, permissive-default Gates, a configurable current-user resolver. `visibleWhen()`/`hiddenWhen()` and category membership remain explicitly not security boundaries — restated in full in `docs/v1-implementation-specification.md`.

---

## 18. Extensibility

**Corrected ranking of the single most consequential change in this plan, again.** An earlier draft ranked this first "the `FieldType` registry," then re-ranked it to "the Component/Page Definition model." This revision removes the `FieldType` registry from v1 scope entirely (§1), so it is no longer a candidate at all — **the Component/Page Definition model (§8) plus the corrected renderer (§11) plus the authoring model (§9 — constructor inference, attributes, enums, sparse overrides) is the consequential change for this release.**

**Deliberate extension points retained:**

- **Custom page models**: resolved via the container or a configurable class in config.
- **Component metadata**: `HasComponentMeta` for icon/category/description/preview image beyond convention-derived defaults.
- **Page/site resolution**: `SiteResolver` (§5).
- **Publishing hooks**: events (§19), plus the Actions remain directly container-bindable.
- **Asset handling**: `MediaUploader` bound as an interface.

**Deferred, not built, for v1:**

- A third-party-extensible `FieldType` registry (§1, §4) — a real, plausible post-1.0 addition once the closed built-in set has proven itself.
- A `RendererDriver` extension point (§11) — not built until a second implementation is real work.
- A plugin system for arbitrary third-party admin pages, or a "register your own settings page" mechanism.

---

## 19. Events and lifecycle hooks

Unchanged. Ship `PagePublishing` (cancellable), `PagePublished`, and `RevisionRestored`. Skip `PageCreating`/`PageSaving`/`PageSaved` as noise. Defer `PageUnpublishing`/`PageUnpublished` until an explicit unpublish action exists, and `ComponentAdded`/`ComponentRemoved` until a real use case appears.

---

## 20. Migration from the current code

A staged path, each stage independently shippable and test-covered, with no period where the suite is red across a commit boundary.

**Stage A — `FieldType` gains cases in place.** Objective, corrected from an earlier draft's "replace the enum with a registry": add `Toggle` and `Number` cases to the existing `FieldType` enum; remove `mapsToClass()` from `Schema`/`Field`. Files: `src/Schema/FieldType.php`, `src/Schema/Schema.php`, `src/Schema/Field.php`. Compatibility: existing test fixtures using `mapsToClass()`/`resolveClass()` (`TestHeroSection`) move to a plain computed method. Existing tests that must keep passing in behavior otherwise: `tests/Unit/SchemaTest.php`, `tests/Feature/FieldPartialsTest.php`, `tests/Feature/PropTamperingRegressionTest.php`. New tests: `Toggle`/`Number` field partial and coercion tests, a `mapsToClass()`-removed characterization test.

**Stage B — Sites and page hierarchy.** Unchanged in shape. Objective: add `sites`, and `pages.site_id`/`parent_id`/`path` (nullable)/`definition` without breaking existing model behavior, plus a backfill migration attaching every existing `Page` to one default `Site`.

**Stage C — Authoring model and registry.** Objective, substantially expanded from an earlier draft's "Sections/Modules umbrella": rename `Sectionable` → `PageBuilderComponent`, `schema()` → `settings()`, `Schema`/`Field` → `Settings`/`Setting`; introduce `PageBuilder::component()`/`components()`/`category()`/`pageDefinition()` behind a `PageBuilder` facade; introduce the constructor-reflection layer (type/default/nullability inference, registration-time mismatch validation) and optional PHP attribute support; introduce backed-enum options handling and the `LinkValue`/`ImageValue` value objects; introduce `page_sections.props` → `settings` (sparse-override storage) and `page_revisions.snapshot`'s fully-resolved shape with captured `id` and `settingsVersion`. This is now the largest single stage in the plan, reflecting where the actual product complexity lives. Definition of done: existing registry test passes (adapted for the rename); new tests cover registration, constructor inference, attribute merging, enum/value-object hydration, and sparse-override resolution end to end.

**Stage D — Renderer correction.** Objective, corrected from an earlier draft's "extract a `RendererDriver` interface with a `BladeRendererDriver` implementation, anticipating a future `LivewireRendererDriver`": extract `SectionRenderer`'s logic into a single, concrete `BladeComponentRenderer` that renders through a real Blade view using `<x-dynamic-component>` (§11) — no interface, no second implementation, no auto-detection to remove because none was ever needed. Compatibility: `tests/Feature/SectionRendererTest.php` and `tests/Feature/RenderPageComponentTest.php` must pass completely unmodified in behavior; a new test confirms a component's own view containing `<livewire:...>` renders and hydrates correctly with zero package-level special-casing.

**Stage E — Admin route/asset scaffold and `page-builder:install`.** Unchanged. Package-owned admin route group, compiled asset publishing, install command, mounting the still-monolithic `PageEditor` before decomposing it.

**Stage F — Editor decomposition.** Unchanged in approach.

**Stage G — Page Definition enforcement.** Objective, tightened from an earlier draft: `ComponentList`/`PageSettingsPanel` respect `allowedComponents()`/`requiredComponents()`/`lockedComponents()`/`defaultComponents()`, using only literal, hand-written component-class arrays — no category-resolution helper is built or offered (§8). New tests: allowed/required/locked enforcement, default-arrangement seeding, and a test confirming no API exists for "allow a category."

---

## 21. Release roadmap

The pre-1.0 track inside this roadmap is replaced by the simpler structure below, per `docs/v1-implementation-specification.md`, which supersedes an earlier draft's Blade-0.1/Livewire-0.2/both-1.0 staging — that staging existed to manage a risk (Livewire adapter complexity) that turned out not to exist once the renderer correction in §11 was made.

### 0.1 — Composition foundation

Stages A–G above, in full: Blade component registration (constructor inference, attributes, enums, value objects), categories, Page Definitions, page administration, drag/drop, iframe preview, draft/publish/revisions (sparse overrides, resolved snapshots), settings versions, public and embedded rendering, package-owned admin assets. **This single release covers what earlier drafts split across "Milestone 0" and "Milestone 1," and what an intermediate draft further split into a Blade-only sub-release and a Livewire sub-release** — collapsed here because the underlying complexity that justified those splits was corrected away, not because ambition was reduced. A component's own view freely using Livewire, Alpine, or anything else is a property of Blade rendering that exists from the first commit, requiring no separate stage.
**Acceptance:** the full sixteen-step user journey (`docs/v1-implementation-specification.md` §3) works end to end, including a component whose own view contains a genuine, hydrating Livewire component.

### 0.2 — Authoring maturity

Only what wasn't already completed cleanly in 0.1: the `migrateSettings()` hook if not built alongside settings versions; the compiled component-definition manifest/cache if reflection-per-request proves costly sooner than expected; improved developer diagnostics for registration-time failures; media-record improvements; richer documentation.

### 1.0 — stable

The complete Blade-component composition workflow is stable; historical revisions render deterministically regardless of later default changes; authoring APIs (constructor inference, attributes, enums, value objects, versions) are documented and stable; no claim of a directly-registered outer Livewire component, and no Vue/React/Inertia adapter claim, anywhere in documentation.

### Milestone 2 — Complete visual composition workflow

**Included:** editor polish (broken-component and missing-context warnings, unpublished-change indicator, responsive/full-page preview, visible validation errors); navigation end to end; fuller site settings; paginated revision history; signed preview links; publish/edit Gate separation.
**Excluded:** any second renderer/outer-adapter; a third-party `FieldType` registry; scaffolding-command polish beyond what 0.1 already ships.
**Dependencies:** 1.0.

### Milestone 3 — Ecosystem and extensibility

**Included:** a third-party `FieldType` registry, if real third-party demand appears (§1, §18); richer `HasComponentMeta` (search, better categorization); events beyond the initial three, if real demand appears; swappable models via config; documentation aimed at third-party consumers.
**Excluded:** a second outer-renderer/adapter; nested composition (`AcceptsChildren`) — removed from the architecture, not merely deferred (§4), and would need to be reintroduced deliberately, not resumed from a dormant interface; global sections/reusable patterns; a full DAM.
**Dependencies:** Milestone 2.

### Milestone 4 — Additional frontend renderers

**Included:** a credible Inertia/Vue or Inertia/React adapter for the _outer_ registered composition unit, if that becomes real, demonstrated work — genuinely separate from "a component's own view uses Vue," which already works today with zero package involvement (§11).
**Excluded from this plan entirely** beyond identifying that this is a different, harder problem needing its own dedicated spec.
**Dependencies:** Milestone 3, plus a future dedicated design spec.

---

## 22. First implementation slice

Unchanged in spirit, corrected in sequence to match §20's stages:

1. `FieldType` gains `Toggle`/`Number`; `mapsToClass()` removed (Stage A) + tests.
2. Sites and page hierarchy schema, including nullable `path` and the `definition` column (Stage B) + tests.
3. The authoring model and registry: `PageBuilderComponent`/`settings()`/`Settings`/`Setting`, `PageBuilder::component()`/`components()`/`category()`/`pageDefinition()`, constructor-reflection inference, attribute support, enum/value-object hydration, sparse-override storage (Stage C) + tests — the largest single item in this sequence, and the one most worth reviewing closely before starting.
4. The corrected `BladeComponentRenderer` (Stage D) + tests, including a nested-Livewire-renders-correctly test.
5. Admin route group and `page-builder:install` (Stage E) + tests.
6. Page index screen + tests.
7. Page creation and page settings screen, including the Page Definition picker (Stage E/G) + tests.
8. Page Definition enforcement — allowed/required/locked components, no category shortcut (Stage G) + tests.
9. Public routing, including a null-`path` page correctly unreachable via the catch-all (Stage B/E) + tests.

**Explicitly out of scope for this first slice**: package asset compilation (CDN SortableJS survives one more slice), the `media` table and `ImageValue` picker, navigation, full editor decomposition (the monolithic `PageEditor` is reused as-is behind the new admin route), settings beyond bare name/homepage, auth Gates beyond middleware configuration, and the `migrateSettings()` hook (version _storage_ is in scope; the migration _hook_ may land in 0.1 or slip to 0.2, per `docs/v1-implementation-specification.md`).

---

## Reconciliation appendix

This plan has been revised twice against corrections in `docs/product-vision.md` and, most recently, `docs/v1-implementation-specification.md`.

**First correction** (audience/category): broadened from "a marketing-website page builder" to "a visual composition layer for existing Laravel applications." Collapsed Sections/Modules/Layouts into one Component concept with capabilities; introduced Page Definitions, pulled into Milestone 1; made `pages.path` optional.

**Second correction** (frontend neutrality): renamed `page_sections.props` to `settings`; removed `mapsToClass()` (first proposed then); introduced `PageBuilder::category()`; and — since reversed by the third correction below — proposed a `LivewireComponentRenderer` alongside `BladeComponentRenderer`, staged as a Blade-0.1/Livewire-0.2/both-1.0 pre-1.0 track.

**Third correction, this revision** (the authoring model and the renderer simplification): the developer workflow is design → Blade components → registration → settings → categories → optional Page Definition → editor composition, where the _outer_ registered unit is always a Blade component whose own view is free to contain Livewire, Alpine, or anything else the host application already supports, with zero package-level involvement in that nesting. This makes the second correction's `LivewireComponentRenderer`/auto-detection/staged-adapter apparatus unnecessary, not merely simpler to build — it solved a problem (supporting a directly-registered outer Livewire component) that the corrected product model never actually has. Consequences, applied throughout this document:

- `LivewireComponentRenderer`, renderer auto-detection, and `->renderer()` are removed. One renderer, `BladeComponentRenderer`, using `<x-dynamic-component>` inside a real compiled Blade view.
- The Blade-0.1/Livewire-0.2/both-1.0 staged track collapses into a single `0.1 — Composition foundation` release (§21).
- `Composable` renames to `PageBuilderComponent`; `schema()`/`Schema`/`Field` rename to `settings()`/`Settings`/`Setting`.
- The `FieldType` registry (the second correction's own headline "most consequential change") is reversed back to a closed, built-in enum for v1 — third-party field-type extensibility is real future work, deferred rather than built, because it isn't required to prove the composition workflow.
- `AcceptsChildren` and `WrapsContent` are removed from the architecture entirely, not merely left unexercised — v1 composition is a flat list, full stop.
- The category-to-Page-Definition convenience helper (`->handles()`) is removed — Page Definitions must list concrete component classes by hand.
- A constructor-inference, PHP-attribute, backed-enum, and typed-value-object authoring model is introduced, materially larger than anything either prior correction specified.
- A sparse-draft/resolved-publish "effective settings" model and a component settings-version mechanism are introduced — both genuinely load-bearing, both frozen precisely in `docs/v1-implementation-specification.md`.
- The preview API renames from `previewLayout()` (ambiguous between "a layout" and "a full view") to `previewView()` (one explicit contract), passing an `Htmlable $composition` value object rather than a raw string.

**Decisions retained across all three corrections:** the `sites` table introduced now; the three-table draft/publish/revision model; the Page Definition concept itself (tightened, not removed); the iframe-based preview architecture and its host-owned, package-never-generates-a-layout discipline; the editor decomposition plan; the settings/navigation/media architecture; the installation system's safe/documented split; the package-asset strategy; the Gate-based auth model.

---

## Recommended destination

This repository becomes an open-source Laravel package that a developer installs into an existing custom application to get a self-contained, database-backed visual composition layer — owning its own admin interface, routing, publishing, and revisions — while every unit of rendered content stays a real Blade component the developer wrote and controls, free to use Livewire, Alpine, or anything else inside it, and every unit of business data stays exactly where it already lived. Never generated markup stored as canonical content, never business data copied into the builder to display it, and never a requirement that the host application reorganise itself around the package.

## What not to build yet

- Multi-site/multi-tenant admin UI, custom domains, any hosted/SaaS product, billing or tenancy UI
- Global/reusable sections or a "patterns" library
- A full digital asset manager
- Page Definitions as a visually-buildable thing
- A directly-registrable outer Livewire (or Vue/React/Inertia) component and any adapter for one
- Nested composition, regions, or an editor-selectable page shell (`AcceptsChildren`/`WrapsContent`) — removed from the architecture, not deferred
- A third-party-extensible `FieldType` registry — real future work, not v1
- A category-to-Page-Definition dynamic-membership shortcut
- A setting-alias mechanism alongside versioned migration
- `disabledWhen()` conditional field state
- A database-wide settings-migration Artisan command
- A generic, arbitrary-key application settings store beyond the fixed, typed CMS settings
- A plugin system for arbitrary third-party admin pages
- Any installer behavior that edits `routes/web.php`, a service provider, or the host's Tailwind/CSS config

## Decisions requiring your sign-off

1. **Introduce the `sites` table now.** Recommended: yes (unchanged across all three corrections).
2. **Rename `Sectionable`/`schema()` to `PageBuilderComponent`/`settings()`.** Recommended: yes.
3. **Reverse the `FieldType` registry decision** — keep it a closed, built-in enum for v1, defer third-party extensibility. Recommended: yes — this is a genuine reversal of a decision this same document previously called "the single most consequential change," and deserves scrutiny for exactly that reason, not a rubber stamp.
4. **Remove `AcceptsChildren`/`WrapsContent` from the architecture entirely**, not just leave them unused. Recommended: yes, but confirm no near-term host application has a concrete, demonstrated need for nested composition or multiple selectable page shells.
5. **Remove the directly-registered outer Livewire renderer, auto-detection, and the Blade/Livewire staged pre-1.0 track**, collapsing to a single `0.1` release. Recommended: yes — this corrects an earlier over-engineering, not a capability loss, since nested Livewire inside a registered Blade component already works with zero adapter.
6. **Adopt the constructor-inference + attributes + enums + value-objects authoring model, and the sparse-override/resolved-snapshot settings model.** Recommended: yes — this is now the largest, most consequential piece of new architecture in the plan, and the one most worth a careful second read before implementation begins.
7. **Ship the admin with its own compiled Tailwind/JS build.** Recommended: yes (unchanged).
8. **Confirm the sequencing in §22** now that it reflects the corrected, collapsed roadmap. Recommended: yes.
