# V1 Implementation Specification

Date: 2026-08-02
Status: Freezes the smallest coherent v1 release — no implementation started
Relationship to prior documents: `docs/product-vision.md` states why this exists and what it refuses to become. `docs/product-redirection-plan.md` lays out the long-term architecture and Milestone 0–4 roadmap, updated in two places by this document: the renderer-adapter correction (its §11) and the resulting pull-forward of Livewire support into Milestone 1 (its §21). This document does not restate settled architecture from either — it freezes the precise, buildable shape of v1, resolves every open question the redirection plan left for "the new spec," and specifies nothing beyond v1.

---

## 1. V1 product definition

V1 is the smallest release that proves the complete product for one developer, one application, and one editor: install the package, register real Blade components — and, once the staged pre-1.0 track below reaches its second stage, real Livewire components — group them into categories, define their editable settings, optionally constrain a kind of page with a Page Definition, compose a page visually against the host application's own real styling, preview it accurately, publish it, and see it rendered — either at a package-owned public path or embedded inside a route the host application already owns. Drafts, revisions, and non-destructive restore carry forward from the existing engine unchanged. Nothing about the host's CSS framework or JavaScript stack is assumed, inspected, generated, or required to be any particular thing.

---

## 2. Governing ownership boundary

**The package owns composition. The application owns presentation.**

| The package owns              | The consuming application owns                        |
| ----------------------------- | ----------------------------------------------------- |
| Component registration        | Component markup                                      |
| Categories                    | Frontend CSS                                          |
| Schemas and settings          | Responsive behaviour                                  |
| Validation                    | Accessibility                                         |
| Placement rules               | JavaScript behaviour                                  |
| Page Definitions              | Frontend assets                                       |
| Ordering                      | Business logic                                        |
| Persistence                   | Domain models                                         |
| Drafts                        | Application data                                      |
| Previews                      | The mapping from a structured setting to presentation |
| Publishing                    |                                                       |
| Revisions                     |                                                       |
| Permissions                   |                                                       |
| The administration experience |                                                       |

Three guarantees follow directly from this boundary, and every section below is checked against them:

1. **The package never stores a presentation decision.** `alignment = left` is stored. Whatever `left` produces — a Tailwind class, a Bootstrap class, a CSS custom property, a Blade branch, nothing at all — is decided inside the developer's own component code, every time, with no exception.
2. **The admin's own styling never reaches a rendered component.** The admin may ship its own compiled, opinionated CSS and JS (§20). A component it renders must look exactly as it would look anywhere else in the host application — this is an architectural guarantee (§13's iframe-based preview), not a discipline the admin's stylesheet happens to maintain.
3. **The package never inspects, generates, or modifies the host's CSS or JS configuration.** Not the Tailwind config, not a Vite manifest, not a Bootstrap import. Where the package needs the host's real assets present (preview, §13), it asks the host to point at something the host already owns.

---

## 3. V1 user journeys

The frozen end-to-end journey a v1 release must support, verbatim:

1. Install the package.
2. Register Components from a service provider.
3. Group them into categories.
4. Define their schemas and editable settings.
5. Register a Page Definition.
6. Open the packaged admin.
7. Create a page.
8. Add components from the visual category picker.
9. Configure settings.
10. Reorder permitted components.
11. Preview using the host application's real styling and frontend stack.
12. Publish.
13. Render at a public package path or inside a host-owned route.
14. Edit the draft without changing the published output.
15. Publish another revision.
16. Restore an earlier revision.

Every section from §6 onward specifies exactly how one or more of these sixteen steps is satisfied.

---

## 4. V1 developer journey

```bash
composer require wearepixel/blade-page-builder
php artisan page-builder:install
php artisan migrate
```

```php
// AppServiceProvider::boot(), or a dedicated PageBuilderServiceProvider
use WearePixel\BladePageBuilder\Facades\PageBuilder;

PageBuilder::category('Headers')
    ->icon('layout-header')
    ->description('Headers and primary page introductions')
    ->components([
        HeaderClassic::class,
        HeaderCentered::class,
        HeaderSplit::class,
    ]);

PageBuilder::category('Commerce')
    ->icon('shopping-bag')
    ->components([
        FeaturedCollection::class,
        ProductGrid::class,
        PromotionBanner::class,
    ]);

PageBuilder::pageDefinition(CollectionPage::class);

PageBuilder::previewLayout(CollectionPage::class, 'collections.show'); // §13 — only needed for context-bound pages
```

```php
// routes/web.php, after the host's own routes
PageBuilder::routes();
```

Open `/admin/pages` (configurable), create a page, choose `CollectionPage` if it applies, compose from the categorized picker, publish. That's the entire developer-facing surface for v1 — one facade, three registration verbs (`component`/`components`/`category`), one Page Definition verb, one routing verb.

---

## 5. Supported renderer adapters

**V1.0 stable ships both a Blade adapter and a Livewire adapter.** Neither Vue, React, nor Inertia is claimed as supported anywhere in this release — not in documentation, not in code comments, not by omission. Getting there is staged (§24) to manage delivery risk, not because Livewire support is architecturally unreachable — see the correction below, which supersedes the redirection plan's earlier, too-pessimistic assessment.

### Why both are reachable in v1.0

The blocker was never "the pipeline's overall shape." Today's `SectionRenderer` instantiates every component with bare `new $class(...$props)` — the wrong construction path for a Livewire component, which must go through Livewire's own mount/hydrate lifecycle, not `new`. The fix is renderer-local:

```php
interface RendererDriver
{
    public function render(string $componentClass, array $settings, string $key): string;
    public function supports(string $componentClass): bool;
}
```

- **`BladeComponentRenderer`** renders each component through a real, compiled Blade view containing `<x-dynamic-component :component="$componentClass" :="$props" />`.
- **`LivewireComponentRenderer`** renders each component through a real, compiled Blade view containing `@livewire($componentClass, $props, key: $key)` — the directive form, not the static `<livewire:name>` tag, because it is the one Livewire/Blade syntax that accepts a runtime class name. This drives Livewire's genuine mount lifecycle: real hydration, stable identity within a single response, actions, events, validation, and nested behaviour, using whatever Livewire frontend assets the host application already loads.

Both go through Laravel's real view engine per component — never manual reflection-based invocation — which is what makes either kind of component render correctly regardless of what else is on the page, and both preserve today's per-component catch-and-skip defensive discipline exactly (§17). This needs real verification against the specific Livewire 4 version this package targets before it is called done — the mechanism is sound and well-documented Laravel/Livewire capability, but "sound in design" and "verified against the exact pinned version" are different claims, and only the second one earns the label "stable."

### Registration and auto-detection

```php
PageBuilder::component(HeaderClassic::class);   // extends Illuminate\View\Component → BladeComponentRenderer, auto-detected
PageBuilder::component(ProductFilter::class);   // extends Livewire\Component → LivewireComponentRenderer, auto-detected

PageBuilder::component(ProductFilter::class)
    ->renderer('livewire'); // explicit override, for the rare ambiguous case
```

Detection is `is_subclass_of($class, \Livewire\Component::class)` / `is_subclass_of($class, \Illuminate\View\Component::class)`, checked at **registration time**, not render time — an unrecognised base class throws immediately when registered, not the first time an editor tries to add it to a page.

### The stored instance is renderer-independent

```json
{
  "handle": "product-filter",
  "settings": {
    "heading": "Find a product"
  }
}
```

No renderer is recorded here, ever. Renderer choice lives in the registry (code), not in persisted data, which is what makes it safe for a developer to change a component's own implementation from Blade to Livewire later without touching a single stored row or writing a migration.

---

## 6. Component registration API

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

One registration verb for the one renderable concept (`component`/`components`), one for grouping (`category`), one separate verb for the one non-renderable concept (`pageDefinition`, §10). No `section()`/`module()`/`layout()`/`widget()`/`block()` split — everything rendered remains one concept, `Composable`.

Fluent overrides available on the value returned by `PageBuilder::component()`:

```php
PageBuilder::component(ProductFilter::class)
    ->handle('product-search')   // override the convention-derived handle
    ->renderer('livewire');      // override renderer auto-detection
```

---

## 7. Categories and Component metadata

### Metadata fields

| Field            | Required?                                                     | Source                                                                                                                                         |
| ---------------- | ------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| `handle`         | Always resolves to something                                  | Convention-derived (`Str::kebab(class_basename($class))`) by default; overridable via `->handle()` or a static `handle(): string` on the class |
| Display name     | Always resolves to something                                  | Convention-derived (`Str::headline(class_basename($class))`) by default; overridable via `HasComponentMeta` or the registration builder        |
| Description      | Optional                                                      | `HasComponentMeta` or the registration builder                                                                                                 |
| Category         | Optional (falls back to an implicit "General" category)       | The registration builder's `PageBuilder::category()->components([...])` call, or `HasComponentMeta`                                            |
| Icon             | Optional                                                      | `HasComponentMeta`, the `category()` builder, or none                                                                                          |
| Preview image    | Optional, admin-picker-only, never affects frontend rendering | `HasComponentMeta` or the registration builder                                                                                                 |
| Renderer adapter | Always resolves to something                                  | Auto-detected (§5); overridable via `->renderer()`                                                                                             |
| Editable schema  | **Required**                                                  | `Composable::schema(): Schema` — the one non-optional method                                                                                   |

**Precedence when both registration-time and static class metadata are supplied: registration-time wins.** It is the developer's explicit act of wiring the component into this application, closer to intent than a static default baked into the class.

### Categories

- **Registration**: `PageBuilder::category(string $name): CategoryRegistrar`, with `->icon()`, `->description()`, `->components(array $classes)`.
- **Ordering**: implicit, by registration order, matching how Laravel already orders service providers and middleware — the order categories are registered in is the order they appear. An explicit `->order(int)` is available for the rare case where registration order across multiple service providers doesn't match desired display order. Components within a category are ordered by their position in the `->components([...])` array; no separate per-component order concept for v1.
- **Uncategorised fallback**: any component registered via bare `component()`/`components()` and never included in a `category()->components([...])` call falls into an implicit "General" category, always rendered last, so nothing registered is ever invisible.
- **Icons**: a name from a small, fixed icon set the package ships inside its own compiled assets (§20) — never an arbitrary raw SVG/HTML string a developer supplies, which would be both an XSS surface and a threat to the admin's visual cohesion. A developer-suppliable custom icon asset is a plausible post-v1 addition, not core.
- **Search**: a simple client-side filter over registered components' display name, description, and category — no dedicated search index needed at this scale.
- **Preview thumbnails**: purely decorative admin-picker metadata, defaulting to a generic placeholder when absent, never required, never affecting frontend output in any way.
- **Empty states**: a fresh install with zero registered components shows an honest, actionable message in the picker ("No components registered yet") rather than blank space.

### How Page Definitions filter the picker without destroying grouping

The picker always groups by category. A Page Definition's `allowedComponents()` (§10), when restricted, narrows _which components appear inside each category group_ — a category with zero surviving components after filtering simply doesn't render, rather than showing an empty, confusing header. **Category membership is never itself a security or placement boundary** — `allowedComponents()` always lists concrete component classes. A developer who wants to allow "the whole Headers category" as a convenience does so by resolving that category to its concrete component list once, at the point the Page Definition's method actually runs (registration/cache time, not a live, ongoing category-membership check):

```php
public function allowedComponents(): array
{
    return [...PageBuilder::category('Headers')->handles(), ProductCollection::class];
}
```

This means adding a new component to the "Headers" category later does not silently, invisibly widen what an existing Page Definition permits — the resolution happened once, at the time that method was written and last deployed, not dynamically against whatever the category currently contains.

---

## 8. Component schema and Settings API

### V1 field types

| Type       | Notes                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `text`     | Single-line string                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `textarea` | Multi-line string                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `richtext` | WYSIWYG HTML, unsanitized by design (documented risk, unchanged from today)                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `number`   | **New** — used directly in the worked example this correction was built around                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| `toggle`   | **New** — boolean                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `select`   | Options list; carries an optional presentation-style hint (`dropdown` default, or `segmented`/`radio`) for the _admin's own field widget_ — this is a rendering hint for the settings form, not a second field type. A "radio or segmented choice" is the same data shape as `select` (one value from a labeled set); giving it a separate `FieldType` would duplicate a type for a presentational difference that belongs, consistently with this whole document, to a rendering layer — here, the admin's own form rendering, not the frontend. |
| `image`    | Now backed by a real `media` record (§11), not a bare path string                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| `link`     | Label + URL (scheme-allowlisted, unchanged) + new-tab toggle                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| `repeater` | Nested, repeatable sub-schema                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     |
| `group`    | Nested, non-repeating sub-schema                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |

Nine types, not eleven — `radio`/`segmented` merge into `select`, per the reasoning above.

**Explicitly not a v1 field type, and not a type this package will ever ship: a CSS class or arbitrary-class field.** A developer wanting one builds a custom `FieldTypeDefinition` (redirection plan §18) for a genuinely unusual case; the package will never encourage it by shipping one.

### Per-field capabilities

- **Defaults**: `->default(...)`, unchanged mechanism from today.
- **Required**: `->required()` — enforced at publish time, not at every keystroke (an incomplete draft is allowed to exist; an incomplete _published_ page is not).
- **Validation rules**: `->rules([...])` accepting ordinary Laravel validator rule strings, layered on top of the existing schema-based coercion/whitelisting (§9) rather than replacing it — no new validation DSL invented.
- **Option labels/values**: `select`'s existing `options` array, unchanged.
- **Help text**: `->help('...')` — a pure UI hint, no persistence or validation implication.
- **Field grouping**: `group`/`repeater` already provide nested settings; visual sectioning of a component's own settings form (tabs, accordions) is an admin-editor UI concern (§15), not a new schema concept.
- **Server-side coercion and unknown-key removal**: unchanged mechanism from today, relocated onto `FieldTypeDefinition::validate()` per type (redirection plan §18).
- **Safe URL validation**: unchanged, on the `link` type specifically.

---

## 9. Conditional setting and presentation model

### Conditional visibility

```php
Schema::make()
    ->toggle('show_action', label: 'Show action')
    ->text('action_label', label: 'Action label')
        ->visibleWhen('show_action', true)
    ->link('action', label: 'Action')
        ->visibleWhen('show_action', true);
```

`visibleWhen()` ships in v1. `hiddenWhen()` ships as its literal inverse — implemented as `visibleWhen()` negated, not a second mechanism — because it reads more naturally in some cases and is nearly free once `visibleWhen()` exists. **`disabledWhen()` is deferred**, not shipped in v1: it introduces a third field state (visible-but-not-editable) with no concrete forcing case in the worked examples that justify it, and the project's own philosophy (redirection plan, vision doc) is explicit about not adding a capability before it earns its place.

**Conditional visibility is a UI convenience. It is never the security boundary, and this must never change:** server-side schema validation and whitelisting run against the _entire declared schema_, unconditionally, regardless of whether `visibleWhen()` currently evaluates true or false for that request. A hidden `action_label` value is still coerced to a string, still whitelisted, still persisted if set — because an editor toggling "show action" off and back on later should reasonably expect their previously-entered label to still be there, and because the moment visibility state were allowed to affect validation, an attacker could trivially bypass a field's coercion by toggling its controlling field before submitting a crafted value. `visibleWhen()` is evaluated exactly once, in the admin's own form-rendering layer, and nowhere else — a second, deliberate line of defense on top of the existing `PropTamperingRegressionTest`-style discipline this package already has.

### The settings/context/props lifecycle

```
saved Component settings
        +
runtime host context
        ↓
registered Component
        ↓
developer-owned markup, classes and behaviour
```

- **Persisted settings** — values an editor controls, stored in `page_sections.settings` (§11), whitelisted and coerced against the component's schema.
- **Runtime context** — supplied by the host route via `renderPage(..., context: [...])`, matched against a component's `RequiresContext::requiredContext()` (§12), never persisted anywhere.
- **Props** — the actual PHP constructor/mount arguments a registered component receives, the merge of settings and context. This is the correct, un-renamed Laravel/Blade term for exactly that mechanism — keeping it distinct from "settings" is not a compelling-reason-to-avoid-renaming situation, it is now two genuinely different things needing two different words.
- **Computed presentation values** — derived inside the developer's own component code from its props, never persisted by the package (see the `mapsToClass()` decision immediately below).

### The `mapsToClass()` decision

**Removed.** `Field::$classResolver`, `resolveClass()`, and `Schema::mapsToClass()` are deleted from the schema API, not kept as optional convenience. Recommendation made decisively, for four reasons:

1. Its mere existence as a schema-builder method implies the package has an opinion about "setting value → CSS class" as a schema-level concern — directly contradicting "the package owns composition, the application owns presentation," even framed as opt-in.
2. An ordinary computed method on the component class is _more_ idiomatic Laravel, not less — testable in isolation, no special DSL:

```php
final class HeaderClassic extends Component implements Composable
{
    public function alignmentClass(): string
    {
        return match ($this->alignment) {
            'left' => 'text-left',
            'centre' => 'text-center',
            'right' => 'text-right',
        };
    }
}
```

3. It costs nothing to remove — the current test fixtures' use of `resolveClass()`/`with()` generalizes trivially to a plain method.
4. Keeping two ways to do the same thing (a schema-attached closure and a computed method) would muddy "who owns presentation" as the teaching point it needs to be.

---

## 10. Page Definition contract

Unchanged from the redirection plan's §8, frozen for v1:

```php
abstract class PageDefinition
{
    public function allowedComponents(): ?array { return null; }   // null = unrestricted
    public function requiredComponents(): array { return []; }
    public function lockedComponents(): array { return []; }       // covers both "cannot be removed" and "cannot be reordered"
    public function defaultComponents(): array { return []; }
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

Not a Component, not rendered, referenced by `pages.definition` as a string handle (§11). A Page Definition is chosen once, at page creation (§15); changing it afterward is a deliberately validated action, not a casual toggle.

---

## 11. Domain model changes

Frozen for v1 (superset already specified in the redirection plan's §4, restated here as exactly what ships now):

| Table            | v1 change                                                                                                                                                                                                                             |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `sites`          | New, one default row, no multi-site UI                                                                                                                                                                                                |
| `pages`          | Adds `site_id`, `parent_id` (nullable), `path` (**nullable** — absent for a host-embedded page), `definition` (nullable string handle)                                                                                                |
| `page_sections`  | **`props` renamed to `settings`**; unchanged shape otherwise                                                                                                                                                                          |
| `page_revisions` | Snapshot entries follow `{"id": ..., "handle": ..., "settings": {...}, "order": ...}` — the `id` captures the _originating_ `page_sections.id` at publish time, purely as a deterministic rendering key (§13), not a live foreign key |
| `site_settings`  | New, minimal: `name`, `homepage_page_id`, `default_meta_title`, `default_meta_description`                                                                                                                                            |
| `media`          | New, minimal: `id`, `disk`, `path`, `filename`, `mime_type`, `size`, `alt_text`, `uploaded_by`                                                                                                                                        |

**Not in v1** (per the redirection plan, restated for clarity): `navigations`/`navigation_items`, the media-backed `site_settings` columns (logo/favicon/social image), any Page Definition database table (it stays code-only, §10).

---

## 12. Runtime context model

```php
interface RequiresContext
{
    public static function requiredContext(): array; // e.g. ['collection']
}
```

```php
PageBuilder::renderPage($page, context: ['collection' => $collection]);
```

A component declaring `RequiresContext` receives any matching key from `$context`, merged into its props alongside its own stored settings. A missing required key is treated exactly like a render exception (§17): silent skip for a visitor, a specific, visible warning for an editor. Context is supplied by whichever call site renders the page — the public catch-all resolves nothing beyond the `Page` itself (routable pages generally don't need context); a host's own route supplies it explicitly; the preview route resolves it via `previewContext()` (§13). **Context is never persisted** — not into `page_sections`, not into `page_revisions.snapshot`, under any circumstance.

---

## 13. Preview architecture

This is the mechanism that makes "the package is fully agnostic about the consuming application's frontend" an architectural guarantee rather than a hope.

### The core mechanism: a real, separate page load, in an iframe

The admin's preview pane is an `<iframe>` pointed at a real preview route (`page-builder.pages.preview`), not HTML injected into the admin's own DOM. This single decision resolves several requirements at once:

- **CSS isolation is structural, not disciplined.** The admin's own compiled CSS/JS (§20) never appears in that document at all — it's a genuinely separate HTTP response with its own `<head>`.
- **Host CSS/JS load exactly as in production**, because the preview response is built the same way a real page response would be — see the host-owned preview layout below.
- **Real Livewire hydration works**, because it's a real page load — Livewire's client-side runtime scans the actual rendered DOM after a real page load, exactly as it does in production, with no special-casing needed for "this is a preview."

### The host-owned preview layout

The package never generates, inspects, or infers a layout. A host configures, once:

```php
PageBuilder::previewLayout('layouts.app');                      // global default
PageBuilder::previewLayout(CollectionPage::class, 'collections.show'); // per Page Definition, for an accurate context-bound preview
```

The default (a marketing-style, routable page) points at whatever root Blade layout the host's real pages already extend — Tailwind, Bootstrap, plain CSS, all load exactly as they would for a real visitor, because it's the same file. A Page-Definition-specific override lets a host reuse the _exact_ view a real visitor would see for a more specialised, context-bound surface (an ecommerce collection page), at the cost of one config line reusing a view the host likely already has. If a host configures nothing at all, the admin falls back to a bare, explicitly-labeled unstyled wrapper ("unstyled fallback — configure `PageBuilder::previewLayout()` to preview using your real design") rather than silently pretending an unstyled fallback is the real thing — an honesty requirement, not a cosmetic one.

### Update mechanism

**Debounced iframe reload, not live DOM patching.** Every meaningful editor change (a settings edit, past its existing debounce window; a reorder; an add/remove) triggers the iframe's `src` to reload. This is simpler and more honest than building a `postMessage`-based partial-patching protocol for v1, and it resolves the "stable Livewire identity during editing" question by removing the premise: each reload is an independent, fresh page render, exactly like a real visitor's browser doing a real navigation — Livewire's wire:key stability requirement only applies _within_ a single response's own DOM-diffing, never across separate page loads.

### Identity for rendering, not "stability"

`wire:key`/`RendererDriver::render()`'s `$key` argument is deterministically derived from `page_sections.id` for draft rendering, and from the captured `id` inside a `page_revisions.snapshot` entry (§11) for published rendering. This gives every rendered component a stable, unique key _within one response_ — sufficient for correctness — without requiring or attempting to preserve identity _across_ requests, which Livewire does not need and this architecture does not promise. Note this is unrelated to, and unaffected by, the admin's own component-list Livewire component (`SectionList`/`ComponentList`), which already correctly keys its own `wire:key` off `page_sections.id` today and needs no change.

### Failure and missing-context display

In draft/admin-context rendering only, a component that throws, or whose `RequiresContext` key is missing, renders as a visible, clearly-marked inline placeholder in the actual preview output ("ProductGrid failed to render: `<message>`" / "ProductGrid requires `collection`, which wasn't supplied") — never a fatal error, never silent. Published rendering strips both cases to nothing, exactly as today.

### Context-dependent pages

```php
PageBuilder::previewContext(CollectionPage::class, function (Page $page) {
    return [
        'collection' => Collection::findOrFail($page->context_reference),
    ];
});
```

Called only by the preview/admin rendering path, never by public rendering (which always gets its context from the host's own real route). **Never persisted into revisions** — its resolved values exist only for the one preview render that used them.

---

## 14. Administration screens

Frozen from the redirection plan's §6 for v1, unchanged in substance; the one addition worth restating here is that the page-creation and page-editor screens' "Add component" experience is the categorized, searchable, thumbnail-bearing picker specified in §7 — "closer to Shopify's customiser or a design library than a plain dropdown," not a flat button list.

---

## 15. Page editor interaction specification

Building on the redirection plan's §7 decomposition (`PageEditor` orchestrator, `ComponentList`, `ComponentFieldPanel`, `PreviewPane`, `RevisionHistory`, `PageSettingsPanel`), frozen interaction detail for v1:

- **Component picker** (`ComponentList`): categorized, searchable, filtered by the page's Page Definition (§7/§10) without losing category grouping (§7).
- **Settings panel** (`ComponentFieldPanel`): renders the selected component's schema, honoring `visibleWhen()`/`hiddenWhen()` (§9) purely as display logic; surfaces server-side validation errors per field for the first time.
- **Reorder, duplicate, delete**: drag-and-drop reorder (retained SortableJS-driven mechanism, now package-shipped, §20); duplicate and delete are new; both are disabled, with an explanatory label, for a component the current Page Definition locks.
- **Preview pane**: the iframe from §13, with a responsive-width toggle and a link to full-page preview.
- **Publish**: unchanged trigger, now also gated by the `page-builder.pages.publish` ability (redirection plan §17).
- **Revision history**: extracted, paginated panel, unchanged restore semantics.
- **Warnings**: a required-but-missing component (Page Definition) and a broken/missing-context component (§13) both surface inline, in the component list, using the same visual language — a problem an editor can see and understand without needing to guess.

---

## 16. Draft, publish and revision behaviour

Unchanged from the existing engine and the redirection plan: `page_sections` is the one live draft tree; publishing snapshots it into an append-only `page_revisions` row (now carrying `settings` and a captured originating `id` per entry, §11); restoring is always non-destructive, producing a new labeled revision rather than rewinding history.

---

## 17. Public and embedded rendering

`RenderPage`'s `render()` method changes from manually concatenating strings (today's `SectionRenderer`) to returning a real Blade view (`page-builder::render`) that loops resolved components and, per component, renders through the appropriate `RendererDriver` (§5) — preserving the exact per-component try/catch discipline that exists today (a broken component is caught, logged, and skipped; it cannot take down the rest of the page), now correctly supporting both adapters because each component is drawn through Laravel's real, compiled view engine rather than manual instantiation.

- **Public**: the catch-all route (§18) resolves a routable `Page` (one with a non-null `path`) and calls `renderPage($page)` with no context.
- **Embedded**: a host's own route/controller resolves its own model and calls `renderPage($page, context: [...])` directly — no package involvement in routing at all for this case.

Both call sites share one rendering primitive; preview (§13) uses the same primitive with `draft: true` and, where applicable, `previewContext()`-resolved context.

---

## 18. Routing

Unchanged from the redirection plan's §5: package-owned admin route group (configurable prefix/middleware), a public catch-all matching only pages with a non-null `path`, `PageBuilder::routes()`/`adminRoutes()`/`publicRoutes()` as the registration API, in-admin and full-page preview routes. **Signed external preview links remain out of v1 scope** (Milestone 2, per the redirection plan) — not required to satisfy the sixteen-step journey in §3, which only requires preview inside the authenticated admin.

---

## 19. Authorization and security

Unchanged mechanism from the redirection plan's §17 (middleware config, permissive-default Gates, a configurable current-user resolver), with two points restated explicitly because this document's subject matter makes them easy to get wrong:

- **`visibleWhen()` is not a security boundary** (§9) — validation is unconditional and authoritative regardless of current visibility state.
- **Category membership is not a security boundary** (§7) — `allowedComponents()` always resolves to concrete classes, never a live category reference.

---

## 20. Package admin assets

Unchanged from the redirection plan's §16: a small, versioned, package-built (not host-built) compiled `app.css`/`app.js`, published via `vendor:publish`, self-hosted, CSP-friendly, no CDN. **New, explicit requirement arising from this document's subject matter**: this compiled CSS must be scoped/namespaced such that it never applies to anything rendered inside a `RendererDriver`'s output — enforced structurally by the iframe-based preview (§13) for the preview case, and trivially true for published rendering, since a host's own page never includes the admin's compiled assets at all.

---

## 21. Installation command

Unchanged from the redirection plan's §15, with one addition: the install command's printed "next steps" output now also mentions configuring `PageBuilder::previewLayout()` (§13) as a required step for an accurate preview — documented, never auto-generated, consistent with "must not attempt to inspect, generate, or modify the host application's CSS configuration."

---

## 22. Explicitly deferred features

Not included in the first stable release unless a genuine forcing case appears:

- Arbitrary HTML editing
- Arbitrary CSS editing
- Tailwind-specific settings
- A package-owned frontend design system
- Generated frontend markup
- Visual creation of new components
- No-code business logic
- Vue or React adapters
- Inertia rendering
- Nested components (`AcceptsChildren` exists as a rendering capability from Milestone 0; no nested-drag-and-drop editor UI in v1)
- Reusable global components
- Navigation management
- A full media library
- Multi-site UI
- Hosted SaaS functionality
- Themes
- A marketplace
- `disabledWhen()` (§9)
- Signed external preview links (§18)

---

## 23. Testing matrix

| Area                             | What must be proven                                                                                                                                                                                                                                                   | Test type                               |
| -------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------- |
| `FieldType` registry             | Existing 8 types unchanged in behavior; `toggle`/`number` work; a host-registered custom type works end to end                                                                                                                                                        | Unit + Feature                          |
| `mapsToClass()` removal          | Deleted from the API; existing fixtures updated to use a computed method instead, with identical rendered output                                                                                                                                                      | Feature (characterization before/after) |
| `settings` rename                | `page_sections.settings` round-trips correctly; `page_revisions.snapshot` entries carry `settings` and a captured `id`                                                                                                                                                | Feature                                 |
| `Composable` rename              | Existing behavior unchanged; old fixtures updated                                                                                                                                                                                                                     | Feature                                 |
| Component registry               | Single (`component()`), bulk (`components()`), and category (`category()->components()`) registration all work; uncategorised fallback; ordering                                                                                                                      | Unit + Feature                          |
| Renderer auto-detection          | A `Illuminate\View\Component` subclass resolves to `BladeComponentRenderer`; a `Livewire\Component` subclass resolves to `LivewireComponentRenderer`; `->renderer()` override works; an unrecognised base class throws at registration time                           | Unit                                    |
| `BladeComponentRenderer`         | Identical output to today's `SectionRenderer` for existing fixtures (characterization)                                                                                                                                                                                | Feature                                 |
| `LivewireComponentRenderer`      | A registered Livewire component genuinely mounts (asserted via presence of real Livewire hydration markers in rendered HTML, e.g. `wire:snapshot`/`wire:id`), receives merged settings+context as props, and its actions/events fire correctly within a composed page | Feature                                 |
| `RequiresContext`                | A component receives supplied context; a missing required key produces the specified visible/silent divergence between admin and public rendering                                                                                                                     | Feature                                 |
| Page Definition enforcement      | `allowedComponents()`/`requiredComponents()`/`lockedComponents()`/`defaultComponents()` all enforced in the editor; category-as-convenience resolves to concrete handles, not a live category reference                                                               | Feature                                 |
| `visibleWhen()`/`hiddenWhen()`   | UI visibility toggles correctly; a hidden field's value is still validated, coerced, and persisted (direct regression test in the spirit of `PropTamperingRegressionTest`)                                                                                            | Feature                                 |
| Preview architecture             | Preview route renders inside the host-configured `previewLayout`; draft vs. published divergence; render-failure and missing-context placeholders appear only in admin context; `previewContext()` resolves and is never persisted                                    | Feature                                 |
| Install command                  | Runs cleanly under Testbench; prints the `previewLayout` next-step                                                                                                                                                                                                    | Feature                                 |
| Frontend neutrality (end-to-end) | A test host app using plain CSS (no Tailwind) renders a composed page with zero admin-CSS classes present in the output                                                                                                                                               | Feature                                 |

---

## 24. Ordered implementation plan

### Stage 0.1 — Blade-only, complete composition workflow

1. `FieldType` registry, including `toggle`/`number`, `mapsToClass()` removed.
2. `page_sections.props` → `settings` rename; `page_revisions.snapshot` gains captured `id`.
3. Sites and page hierarchy schema (`path` nullable, `definition` column).
4. Component registry facade: `Composable` rename, `PageBuilder::component()`/`components()`/`category()`, `PageBuilder::pageDefinition()`.
5. `BladeComponentRenderer` via real Blade-view rendering (`<x-dynamic-component>`), replacing manual instantiation — zero behavior change, verified by characterization tests.
6. `renderPage($page, draft, context)` primitive, `RequiresContext` handling.
7. Admin routes and `page-builder:install`.
8. Page index, page creation (with Page Definition picker), page settings screens.
9. Categorized, searchable component picker in the editor.
10. Preview architecture (§13) — iframe, `previewLayout()`, `previewContext()` — Blade only.
11. Public routing (path-based) and embedded rendering via `renderPage()`.
12. Publish/revision behavior verified against the new `settings`/captured-`id` shape.

### Stage 0.2 — Livewire adapter

13. `LivewireComponentRenderer` via the `@livewire()` directive path.
14. Renderer auto-detection and `->renderer()` override.
15. `wire:key`/identity verification in both preview (iframe reload) and published rendering, per §13.
16. End-to-end verification that a registered Livewire component's full lifecycle (mount, hydration, actions, events, validation) works correctly inside both preview and published rendering, using the host's own Livewire frontend assets.
17. Confirm the editor's picker, settings panel, and reorder/lock behavior are already renderer-agnostic (they should be, since none of §7/§15's design is Blade-specific) — fix anything that isn't.

### 1.0 — both declared stable

18. Full testing matrix (§23) green for both adapters.
19. Documentation covers both adapters with no overstated claims about Vue/React/Inertia.
20. Frontend-neutrality end-to-end test (§23) passes against a plain-CSS host fixture.

---

## 25. Stable v1.0 definition of done

- The full sixteen-step journey (§3) works end to end, demonstrated with at least one Blade component and one Livewire component composed on the same page.
- Frontend neutrality is verified, not asserted: a test host application using plain CSS (no Tailwind, no Bootstrap) renders a composed page with zero admin-originated CSS in the output.
- Page Definition guardrails (allowed/required/locked/default) are enforced in the editor and covered by tests.
- The settings/context/props lifecycle (§9) behaves exactly as specified, including the security-boundary guarantees around `visibleWhen()` and category membership.
- No documentation, code comment, or admin UI copy claims Vue, React, or Inertia support.
- Package admin assets are compiled and self-hosted; no CDN dependency anywhere.
- `page-builder:install` runs cleanly under Testbench and prints the `previewLayout` next step.
- The full testing matrix (§23) passes.

---

## 26. Decisions requiring sign-off

1. **`mapsToClass()` is removed, not deprecated.** This is a real deletion touching every existing test fixture that uses it (`TestHeroSection`). Confirm the migration cost is acceptable now, while there are still zero real installs.
2. **`page_sections.props` renames to `settings`.** A small, mechanical rename, but one that touches persisted JSON shape and every place that reads it. Confirm now is the right time (it is cheap pre-release, expensive after).
3. **The staged 0.1/0.2/1.0 boundary** (§24) — confirm Blade-only is an acceptable first deliverable before Livewire lands, given the corrected understanding that Livewire support is not architecturally blocked, only sequenced for delivery-risk reasons.
4. **`previewLayout()` requires host wiring** as a new, explicit "day 1" step not previously specified — confirm this is an acceptable addition to the installation journey (§4, §21) in exchange for the frontend-neutrality guarantee it enables.
5. **Radio/segmented choice merges into `select`** as a presentation hint rather than shipping as its own field type — confirm this reading of "the same data shape, a different widget" is correct and doesn't foreclose a real future need for genuinely different underlying data.
6. **`disabledWhen()` is deferred**, not shipped in v1 — confirm no current worked example or near-term use case actually needs a visible-but-uneditable field state.
7. Everything carried over from the redirection plan's own sign-off list — the `sites` table, the `FieldType` registry replacement, the `Composable` rename, Page Definitions in Milestone 1, the compiled admin asset build, and now the corrected Livewire pull-forward (its item 9) — still stands and is not being re-litigated here.

---

## 27. Required final recommendations

**Should the stable v1.0 include both Blade and Livewire rendering adapters?**
Yes, decisively. The renderer-adapter design in §5 resolves the actual technical blocker with far less new architecture than the redirection plan's earlier assessment projected; both belong in v1.0.

**If staged pre-1.0, what exact capability belongs in 0.1 and 0.2?**
0.1: the entire composition workflow — registration, categories, schemas, Page Definitions, admin, install, preview, publishing, revisions, public and embedded rendering — using the Blade adapter only. 0.2: the Livewire adapter, renderer auto-detection, and identity verification, added on top of an already-complete, already-tested 0.1 without touching its editor or admin surface. See §24 for the itemized sequence.

**What should the public Component contract be named?**
`Composable`. One required method, `schema(): Schema`, unchanged from today's `Sectionable` in everything but name.

**What is the final service-provider registration API?**
`PageBuilder::component(Class::class)`, `PageBuilder::components([...])`, `PageBuilder::category(string)->icon()->description()->components([...])`, with `->handle()` and `->renderer()` fluent overrides on the value `component()` returns, and `PageBuilder::pageDefinition(Class::class)` as the one separate, non-rendering registration verb.

**Should `mapsToClass()` be retained or removed?**
Removed. Replaced by ordinary computed methods on the component's own class (§9). Retaining it, even as "optional convenience," would keep a schema-level opinion about CSS-class mapping alive in exactly the place this whole correction exists to remove it from.

**Which field types are genuinely required before 1.0?**
Nine: `text`, `textarea`, `richtext`, `number`, `toggle`, `select` (with an optional presentation-style hint covering radio/segmented), `image`, `link`, `repeater`, `group`. No CSS-class field type, ever, as a first-party type.

**How does preview load the host application's actual frontend assets?**
Via a real, separate HTTP response — an iframe pointed at a preview route — rendered through a host-configured (never package-generated) `previewLayout`, which is or extends the host's own real root Blade layout, so its actual Tailwind/Bootstrap/plain-CSS/JS loads exactly as it would for a real visitor. See §13.

**How are Livewire component identities kept stable through reorder and revision restore?**
They don't need to be "stable" across requests at all, once preview updates via debounced iframe reload rather than live DOM patching — each reload is an independent, fresh render, exactly like a real page navigation. `wire:key` is deterministically derived from `page_sections.id` (draft) or a captured `id` inside the revision snapshot (published), which is sufficient for correctness within a single response without requiring or promising cross-request stability. See §13.

**Which current architecture decisions must change before implementation begins?**
The renderer must move from manual component instantiation to real Blade-view-based rendering for both adapters (§5); `page_sections.props` renames to `settings` (§11); `mapsToClass()` is deleted (§9); the `FieldType` enum becomes a registry, `Sectionable` renames to `Composable`, and Page Definitions are pulled into Milestone 1 — all three already decided in the redirection plan and unaffected by this document except to confirm they still hold.

**What is the smallest first implementation phase that produces visible product progress?**
Stage 0.1's own early slice (§24, items 1–11): the FieldType/settings/schema foundation, the Component registry and Page Definition contract, the corrected Blade renderer, admin routes and install, page index/creation/settings with the categorized picker, and preview and public/embedded rendering — a fully working, visibly demoable, Blade-only product, before any Livewire work begins.
