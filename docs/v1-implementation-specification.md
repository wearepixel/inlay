# V1 Implementation Specification

Date: 2026-08-02
Status: Freezes the smallest coherent v1 release — no implementation started
Revision note: this replaces the first draft of this document, which over-complicated frontend rendering by treating a directly-registered Livewire component as a core v1 requirement, with its own renderer, auto-detection, and a staged Blade-then-Livewire delivery plan. That entire apparatus is removed in this revision, not simplified — the underlying problem it solved does not exist once the outer registered unit is understood correctly as always a Blade component, free to contain Livewire (or anything else) internally with zero package involvement. See §5.
Relationship to prior documents: `docs/product-vision.md` states why this exists and what it refuses to become. `docs/product-redirection-plan.md` lays out the long-term architecture and roadmap, reconciled against this revision — see its own reconciliation appendix. This document does not restate settled architecture — it freezes the precise, buildable shape of v1.

---

## 1. V1 product definition

V1 is the smallest release that proves the complete product for one developer, one application, and one editor. The actual workflow it proves:

1. A developer translates a design into normal application frontend code.
2. They break the design into reusable Blade components.
3. They register those components with the package.
4. They define the settings an editor may change.
5. They group the components into categories.
6. They optionally define Page Definition constraints.
7. Editors add, configure, reorder, preview, and publish those components.

**The registered outer unit is always a normal Blade component.** That Blade component remains completely owned by the consuming application and may internally use anything the application already supports — nested Blade components, Livewire components, Alpine, AJAX, ordinary JavaScript, a Vue or React island, forms, domain services, Eloquent models, ecommerce functionality, custom frontend assets — with no special rendering adapter of any kind:

```blade
<section>
    <h2>{{ $heading }}</h2>

    <livewire:contact-form :campaign="$campaign" />

    <div x-data="{ open: false }">
        ...
    </div>
</section>
```

The package's job is only to resolve the registered Blade component, determine its effective settings, pass those settings and any approved runtime context into it, render it through Laravel's real Blade component system, and preserve ordering, drafts, publishing, and revisions. Nothing about the host's CSS framework or JavaScript stack is assumed, inspected, generated, or required to be any particular thing.

---

## 2. Governing ownership boundary

**The package owns composition. The application owns presentation.**

| The package owns              | The consuming application owns                       |
| ----------------------------- | ---------------------------------------------------- |
| Component registration        | Component markup                                     |
| Categories                    | Frontend CSS                                         |
| Schemas and settings          | Responsive behaviour                                 |
| Validation                    | Accessibility                                        |
| Placement rules               | JavaScript behaviour                                 |
| Page Definitions              | Frontend assets                                      |
| Ordering                      | Business logic                                       |
| Persistence                   | Domain models                                        |
| Drafts                        | Application data                                     |
| Previews                      | The mapping from structured settings to presentation |
| Publishing                    |                                                      |
| Revisions                     |                                                      |
| Permissions                   |                                                      |
| The administration experience |                                                      |

The package stores:

```json
{
  "handle": "header-hero",
  "version": 1,
  "settings": {
    "heading": "Summer collection",
    "imageSize": "small"
  }
}
```

The developer decides how those values affect markup and presentation, entirely inside their own component code:

```blade
<img
    src="{{ $image->url }}"
    @class([
        'max-w-md' => $imageSize === ImageSize::Small,
        'w-full' => $imageSize === ImageSize::Large,
    ])
>
```

The client never edits HTML, Blade, PHP, CSS, Tailwind classes, Bootstrap classes, JavaScript, or business logic.

---

## 3. V1 user journeys

Unchanged, the frozen end-to-end journey a v1 release must support:

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

---

## 4. V1 developer journey

```bash
composer require wearepixel/inlay
php artisan inlay:install
php artisan migrate
```

```php
final class Hero extends \Illuminate\View\Component implements InlayBlock
{
    public function __construct(
        public string $heading = 'Welcome',
        public string $description = '',
        public HeroAlignment $alignment = HeroAlignment::Left,
        public ImageSize $imageSize = ImageSize::Large,
        public bool $showAction = false,
        public ?LinkValue $action = null,
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()
            ->text('heading')
                ->label('Heading')
                ->required()
                ->maxLength(100)
            ->textarea('description')
                ->label('Description')
            ->select('alignment')
                ->options(HeroAlignment::class)
            ->select('imageSize')
                ->options(ImageSize::class)
            ->toggle('showAction')
                ->label('Show action')
            ->link('action')
                ->label('Action')
                ->visibleWhen('showAction', true);
    }

    public function imageClasses(): string
    {
        return match ($this->imageSize) {
            ImageSize::Small => 'max-w-md',
            ImageSize::Large => 'w-full',
        };
    }

    public function render(): \Illuminate\View\View
    {
        return view('components.hero');
    }
}
```

```php
// AppServiceProvider::boot()
Inlay::category('Headers')
    ->icon('layout-header')
    ->components([Hero::class]);

Inlay::pageDefinition(CollectionPage::class); // only if constraints are needed

Inlay::routes(); // in routes/web.php, after the host's own routes
```

Open `/admin/pages`, create a page, compose from the categorized picker, publish. That's the entire developer-facing surface for v1: one facade, three registration verbs (`component`/`components`/`category`), one Page Definition verb, one routing verb, no renderer choice to make anywhere.

---

## 5. The rendering model — corrected

### The mistake in the first draft of this document

The first draft of this specification treated a directly-registered Livewire component as a core v1 requirement, proposing `LivewireComponentRenderer` alongside `BladeComponentRenderer`, auto-detection between the two based on which base class a component extends, an explicit `->renderer('livewire')` override, and a staged pre-1.0 delivery plan (Blade first, Livewire second, both declared stable at 1.0) to manage the perceived complexity of getting there.

**None of that is necessary**, because the actual developer workflow (§1) never registers a Livewire component as the _outer_ unit at all. A developer translates a design into Blade components. Those components may use Livewire _internally_, in their own view, exactly as any Blade view in any Laravel application already can. The package never needs to know, care, or provide an adapter for that — it is ordinary Blade/Livewire behaviour, entirely owned by the host application.

### The v1 rendering path — one renderer, empirically verified

**`<x-dynamic-component>` does not accept a fully-qualified class name directly** — confirmed by running it against this package's pinned Laravel/Livewire versions before writing any production code: passing a class string throws `Unable to locate a class or view for component [...]`. Per this document's own instruction for exactly this outcome, the renderer resolves each component to a **registered Blade component alias** instead — never falling back to manual `new $componentClass(...)` construction.

**A second, more consequential finding, from running the spike as part of the full suite rather than in isolation: `<x-dynamic-component>` itself is not safe to use for this, even with an alias.** `Illuminate\View\DynamicComponent` memoises its `ComponentTagCompiler` instance and every class it has ever resolved into `protected static` properties that live for the entire PHP process. The first time _anything_ renders a dynamic component, that compiler snapshot — and the alias table it was built from — is frozen for the rest of the process; an alias registered afterward (a different component class, a fresh application boot, a long-running worker meeting a component type it hasn't rendered before) silently fails to resolve. This is not a testing artifact to work around — it would affect a real Octane worker the same way it affected this package's own test suite. The fix is to avoid `<x-dynamic-component>` entirely: since the alias is already a known PHP string at the point of rendering, it's interpolated directly as a **literal tag name**, which Blade compiles as an ordinary, non-dynamic component tag against the live compiler instance at compile time — no static caching, no process-lifetime staleness.

```php
final class BladeComponentRenderer
{
    public function render(string $componentClass, array $settings): string
    {
        $alias = 'inlay-'.md5($componentClass);

        Blade::component($alias, $componentClass); // idempotent; cheap to call on every render

        $bindings = collect(array_keys($settings))
            ->map(fn (string $key) => ":{$key}=\"\$settings['{$key}']\"")
            ->implode(' ');

        // Blade::render() also caches its compiled output by template TEXT,
        // not by data - two different aliases can produce identical
        // template text (same binding variable names), so deleteCachedView
        // forces a fresh compile every render rather than risking a stale
        // file from a different alias's resolution.
        return Blade::render(
            "<x-{$alias} {$bindings} />",
            ['settings' => $settings],
            deleteCachedView: true,
        );
    }
}
```

This is a real, compiled Blade template — rendered through Laravel's genuine view/compiler pipeline, not a manual reflection-based invocation. This replaces today's `SectionRenderer`, which constructs each component with bare `new $componentClass(...$props)` and then manually calls `render()->with($data)->render()`, bypassing that pipeline entirely. The fix is not "add a second renderer for Livewire." It is "stop bypassing the view engine for the one renderer that exists," which also happens to be exactly what makes nested Livewire work correctly with zero further effort: because the outer component's own `.blade.php` view is now rendered through Blade's real compiler (as part of the alias tag resolving and rendering it), any `<livewire:...>` tag or other Blade directive inside that view compiles and renders exactly as it would in any other Laravel view. **This is not a theoretical claim** — `tests/Feature/RendererMechanismSpikeTest.php` proves it directly: a component's own view containing `<livewire:spike-inline-widget />` renders and produces real `wire:snapshot` hydration markup, using this exact mechanism, with zero package-level Livewire awareness.

The spike also confirms two supporting details this mechanism depends on: an enum or `LinkValue` setting is passed to the renderer **already hydrated** (the scalar-to-case and array-to-object conversion is the settings-resolution step's job, §10–§11, not something Blade does automatically), and an **unexposed constructor dependency resolves through Laravel's own container** — resolving a component tag ultimately calls the container's `make()` with the supplied bindings as named parameters, so a type-hinted, non-exposed parameter (an injected repository or service) is autowired exactly as it would be for any other Blade component, with no special handling needed for it in the renderer at all.

```blade
{{-- resources/views/components/hero.blade.php — entirely host-owned --}}
<section>
    <h2>{{ $heading }}</h2>
    <livewire:contact-form :campaign="$campaign" />
</section>
```

**This requires no special rendering adapter.** It is not a package concern at all.

### What this removes, decisively

- `LivewireComponentRenderer` — removed from v1. Not deferred, not staged for 0.2 — removed, because the problem it solved does not exist.
- Renderer auto-detection between Blade and Livewire base classes — removed. There is one renderer.
- `->renderer('livewire')` on the registration API — removed.
- Any claim that a directly-registered outer Livewire component is supported in v1 — removed from every document.
- The Blade-0.1/Livewire-0.2/both-1.0 staged pre-1.0 plan — collapsed into a single `0.1 — Composition foundation` release (§20).

### What is retained as future possibility, without speculative architecture now

A different _outer_ composition adapter — an Inertia/Vue or Inertia/React adapter for the registered unit itself, as opposed to a Vue/React island mounted inside an ordinary Blade view (which already works today, no adapter needed) — remains a plausible, genuinely separate piece of future work. It is not designed further here, and no interface (`RendererDriver` or similar) is built now in anticipation of it. The possibility is preserved because nothing in this design prevents extracting an interface later, when a second real implementation justifies one — not because a seam sits unused today. Building that seam now, for a single implementation, would be exactly the kind of speculative architecture this correction exists to remove.

### Internal Livewire usage in the admin is unaffected

The packaged administration interface continues to use Livewire internally, as it already does today (`PageEditor` and its decomposed children, `docs/product-redirection-plan.md` §7) — this was never in question and has nothing to do with whether a _registered page component_ can be a Livewire class.

---

## 6. Component registration API

```php
Inlay::component(HeaderClassic::class);

Inlay::components([
    HeaderClassic::class,
    HeaderCentered::class,
]);

Inlay::category('Headers')
    ->icon('layout-header')
    ->description('Headers and page introductions')
    ->components([
        HeaderClassic::class,
        HeaderCentered::class,
        HeaderSplit::class,
    ]);
```

One registration verb for the one renderable concept (`component`/`components`), one for grouping (`category`), one separate verb for Page Definitions (`Inlay::pageDefinition()`, §13). No renderer parameter anywhere — there is exactly one renderer (§5).

---

## 7. The public Component contract

```php
interface InlayBlock
{
}
```

**`InlayBlock` is a marker interface — it declares no methods.** An earlier draft of this document required `public static function settings(): Settings` on the interface itself, which is incompatible with also permitting attribute-only components that declare no `settings()` method at all — PHP cannot make an interface method simultaneously required and optional. `settings()` remains fully supported and is preferred for complex schemas, but it is one of two ways a component may declare its editor-facing metadata, not a contractual requirement:

```php
public static function settings(): Settings; // optional — present on the class if used
```

The registry builds a component's resolved settings definition from up to three sources: constructor-property attributes (§9), the optional fluent `settings()` method, and registration-time metadata (a category, icon, or description supplied to `Inlay::component()`). Where both attributes and `settings()` declare the same setting, the fluent declaration overrides the attribute's metadata for that setting. Constructor PHP type, nullability, and default value are authoritative regardless of which style declared the field. **No trait is required merely to make an attribute-only component valid** — the marker interface plus attributes on the constructor is sufficient on its own.

### A constructor parameter is not a setting unless explicitly exposed

Not every constructor parameter is an editor setting. A component's constructor may contain injected services and internal application dependencies that must never become editable:

```php
public function __construct(
    private ProductRepository $products,
    #[Text(label: 'Heading')]
    public string $heading = 'Featured products',
    #[Number(label: 'Limit')]
    public int $limit = 8,
) {}
```

`ProductRepository $products` is never a setting — nothing declares it as one. A constructor parameter becomes an editor-facing setting **only** when explicitly declared, either by a supported attribute or by an entry in `settings()`. This is what a constructor parameter provides once (and only once) it's exposed this way: PHP type, nullability, enum or value-object type, and runtime default. Everything not exposed is left entirely alone — the renderer never touches, inspects, or attempts to supply a value for a parameter no attribute or `settings()` entry named. This is what makes it structurally true, not merely policy, that **editors can only change what a developer explicitly exposed** (§2's ownership boundary made concrete at the constructor level).

Unexposed, container-resolvable dependencies like `$products` above are satisfied the ordinary way Laravel already resolves Blade components: rendering an outer component through its registered alias tag (§5) goes through the service container, which auto-resolves a type-hinted, non-explicitly-supplied constructor parameter exactly as it would for any other Blade component in the application — the renderer supplies data only for the settings it knows about (the exposed ones, plus any matching runtime context), and the container fills in everything else. No special-casing is needed here; it is Laravel's existing behaviour, not new package logic. This is verified directly by the renderer spike (§5, `tests/Feature/RendererMechanismSpikeTest.php`).

**Registration must reject an attribute or `settings()` declaration with no corresponding constructor parameter** — the same validation direction as before, now stated alongside its converse: exposure is opt-in per parameter, and a declared setting with no parameter to attach to is a registration-time error, not a runtime one.

```php
final class Hero extends Component implements InlayBlock
{
    public function __construct(
        public string $heading = 'Welcome',
        public string $description = '',
        public HeroAlignment $alignment = HeroAlignment::Left,
        public ImageSize $imageSize = ImageSize::Large,
        public bool $showAction = false,
        public ?LinkValue $action = null,
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()
            ->text('heading')
                ->label('Heading')
                ->required()
                ->maxLength(100)
            ->textarea('description')
                ->label('Description')
            ->select('alignment')
                ->options(HeroAlignment::class)
            ->select('imageSize')
                ->options(ImageSize::class)
            ->toggle('showAction')
                ->label('Show action')
            ->link('action')
                ->label('Action')
                ->visibleWhen('showAction', true);
    }

    public function imageClasses(): string
    {
        return match ($this->imageSize) {
            ImageSize::Small => 'max-w-md',
            ImageSize::Large => 'w-full',
        };
    }

    public function render(): View
    {
        return view('components.hero');
    }
}
```

The `Settings::make()` fluent chain is a natural extension of the current codebase's existing `Schema` builder, which already tracks "the last field defined" internally to support `mapsToClass()`-style chaining (`src/Schema/Schema.php`). This revision generalizes that same mechanism: every field-defining call (`->text('heading')`) returns a continuation that further modifier calls (`->label()`, `->required()`, `->maxLength()`, `->visibleWhen()`) attach to, rather than each field type accepting one call with an ever-growing list of named arguments. This is an incremental extension of a pattern already proven in this codebase, not a new mechanism invented for this document.

---

## 8. Constructor typing as the source of truth

**The component constructor is the runtime source of truth for parameter names, PHP types, nullability, backed-enum types, value-object types, and default values.** `settings()` declares editor-specific behaviour only: field widget, label, help text, validation, visibility, options, and grouping. It never re-declares the type or default — those are read, once, via reflection.

```php
public function __construct(
    public string $heading = 'Welcome',
    public bool $showAction = true,
    public ImageSize $imageSize = ImageSize::Large,
) {}

public static function settings(): Settings
{
    return Settings::make()
        ->text('heading')
            ->maxLength(100)
        ->toggle('showAction')
        ->select('imageSize')
            ->options(ImageSize::class);
}
```

The package infers the default heading, the default toggle state, the enum type, and nullability, all from the constructor. **Reflection happens at registration time in development, and once, at compiled-manifest-build time, for production** (§16) — never repeated on every render request.

### Constructor defaults are the exclusive source of truth for top-level settings

For a setting that maps directly to a top-level constructor parameter, **the constructor default is the only permitted default — there is no competing schema-level default for the same setting.** An earlier draft of this document allowed `->text('heading')->default('Welcome')` as an escape hatch alongside a constructor default. That escape hatch is removed for top-level settings: there must be exactly one runtime source of truth, and permitting two (even if they're expected to agree) invites exactly the kind of silent drift this whole authoring model exists to prevent.

```php
public string $heading = 'Welcome'; // authoritative — the only default
```

```php
->text('heading')->default('Welcome'); // not permitted for a top-level, constructor-backed setting
```

**A required, non-nullable, top-level exposed setting with no constructor default fails registration.** It does not fall back to a schema-level default instead — there is no schema-level default to fall back to for a top-level setting, by design.

**Schema-level defaults remain legitimate only where structurally necessary: inside a `repeater` or `group` field's own item sub-schema, for a sub-field that doesn't map to a top-level constructor parameter of the outer component at all.** A repeater's stored value is an array of items (§12); an individual item's sub-fields aren't named constructor parameters of `Hero` or `CollectionPage` — they're keys within one array-typed constructor parameter (`public array $testimonials = []`). There is no constructor to reflect a per-sub-field default from in that case, so `->text('quote')->default('')` inside a repeater's item schema is the correct, and only, place a schema-level default belongs. The boundary is exact: a schema-level default is permitted exactly where there is no corresponding top-level constructor parameter to be authoritative instead, never as an alternative to one that exists.

### Registration-time validation

The following fail immediately, with a clear developer-facing exception, at the point a component is registered — never discovered later at render time:

- An attribute or `settings()` field with no matching constructor parameter (§7).
- A `select`'s declared enum (via `->options(EnumClass::class)`) that doesn't match the constructor parameter's actual type-hint.
- A required, non-nullable, top-level constructor parameter with no default — with no schema-level default permitted to substitute for it (see above).
- A duplicate setting name declared twice.

---

## 9. PHP attributes

An optional, elegant authoring style for simple components, alongside — not instead of — the fluent `Settings::make()` API, which remains fully supported and is preferred for complex schemas.

```php
final class Hero extends Component implements InlayBlock
{
    public function __construct(
        #[Text(label: 'Heading', help: 'Keep this under 80 characters', maxLength: 80)]
        public string $heading = 'Welcome',

        #[Textarea(label: 'Description')]
        public string $description = '',

        #[Select(label: 'Image size')]
        public ImageSize $imageSize = ImageSize::Large,

        #[Toggle(label: 'Show action')]
        public bool $showAction = false,

        #[Link(label: 'Action', visibleWhen: ['showAction', true])]
        public ?LinkValue $action = null,
    ) {}

    public function render(): View
    {
        return view('components.hero');
    }
}
```

Ordinary PHP language attributes, available within the package's existing `^8.2` baseline — this feature does not require PHP 8.4 or property hooks, and is not built around either.

### Merge and precedence rules

- A component may use attributes only.
- A component may use `settings()` only.
- If both are present, they are merged by setting name.
- **Fluent (`settings()`) declarations override attribute metadata for the same setting name.**
- Constructor type and default remain authoritative regardless of which authoring style declared the field.
- **Conflicting field types between an attribute and a fluent declaration for the same setting fail during registration**, never resolved silently.

---

## 10. Backed enums as first-class setting values

```php
enum ImageSize: string implements SettingOptions
{
    case Small = 'small';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small',
            self::Large => 'Large',
        };
    }
}
```

```php
->select('imageSize')
    ->options(ImageSize::class)
```

The package displays labeled options in the admin, persists the backed scalar value (`'small'`), hydrates the enum case before constructing the Blade component, and validates that a stored value corresponds to a real case. `SettingOptions` (a lightweight interface with one method, `label(): string`) is only needed when labels can't be derived sensibly — an enum that doesn't implement it gets a convention-derived label from its case name (`Str::headline($case->name)`).

### Orphaned enum values are never silently substituted in every context alike

A stored value that no longer corresponds to a real enum case (a case was removed after content referencing it was created) is not treated the same way everywhere — an earlier draft of this document silently fell back to the constructor default in all contexts, which hides a real content problem from the one audience (an editor or developer) positioned to fix it:

- **Public rendering**: use the constructor default, log a structured warning (component handle, field name, orphaned value, page id), and continue rendering normally. A visitor never sees an error.
- **Draft/admin preview**: use the constructor default _and_ render a visible, specific warning in the preview output — the same inline-placeholder mechanism used for a broken or missing-context component (`docs/product-redirection-plan.md` §11) — so the person who can fix it sees that something needs attention.
- **Publishing**: **block publication** until the invalid setting is corrected or migrated. `PublishPage` resolves every component's effective settings before writing a new revision; if any resolution encounters an orphaned enum value, the publish action fails with a specific, actionable error naming the component and field, rather than freezing a known-bad value into a historical snapshot.

The same distinction — warn-and-continue for visitors, visible warning for editors, hard block at publish — applies to a failed settings-version migration where no `migrateSettings()` path exists for the stored version (§14): the visitor and admin behaviour mirror the enum case exactly, and publishing a component with settings that can't be resolved against its current definition is blocked for the same reason.

---

## 11. Typed value objects

Complex setting values hydrate into typed value objects rather than loose arrays, where practical. V1 ships two: `LinkValue` and `ImageValue`.

```php
final readonly class LinkValue
{
    public function __construct(
        public string $label,
        public string $url,
        public bool $openInNewTab = false,
    ) {}
}
```

- **Serialization**: a simple, JSON-compatible array (`{"label": ..., "url": ..., "open_in_new_tab": ...}`), stored as the setting's persisted representation — never the object itself.
- **Hydration**: the stored array is converted into a real `LinkValue`/`ImageValue` instance before being passed into the component's constructor — a component's `?LinkValue $action` parameter always receives a genuine value object or `null`, never a raw array.
- **Null handling**: a nullable value-object setting with no override and a `null` default passes `null` cleanly; there is no "empty value object" placeholder.
- **Validation**: the existing URL-scheme allowlisting logic (`isAllowedUrlScheme()`, today inline in `PageEditor`) relocates onto `LinkValue`'s own coercion step, unchanged in behavior. `ImageValue` wraps a reference into the `media` table (`docs/product-redirection-plan.md` §14), with convenience accessors (`->url()`, `->alt()`) resolved at hydration time.
- **Forward compatibility**: both are small, `readonly` classes with a fixed shape. Adding a new property later needs a constructor default to avoid breaking already-serialized data — the same discipline as any schema evolution, coordinated with settings versioning (§14) if the shape change is significant enough to warrant it.

**No value objects are shipped for presentation concepts** — spacing, CSS classes, or anything else that belongs on the application side of the ownership boundary (§2). Value objects exist only for genuinely structured editorial data.

---

## 12. Effective settings and sparse overrides — load-bearing

**Draft component instances store only editor overrides, not every field's current value.**

```
constructor/settings defaults
        +
stored editor overrides
        =
effective settings
```

A developer can add a brand-new field to an already-in-production component —

```php
public ImageSize $imageSize = ImageSize::Large
```

```php
->select('imageSize')->options(ImageSize::class)
```

— without a database update for any existing instance. Every existing `Hero` immediately, correctly behaves as `imageSize = Large`. When an editor picks `Small`, only that one override is persisted.

This is a smaller change than it first appears: today's codebase already exhibits an _accidental_ version of this behaviour, because `SectionRenderer::render()`'s `new $componentClass(...$props)` is a named-argument spread — a key genuinely absent from `$props` already falls back to the constructor's own default, purely as a side effect of PHP's argument-spreading semantics. What changes is making this **deliberate rather than accidental**: `PageEditor::addSection()` currently writes every declared field's value explicitly at creation time; v1 stops doing that, storing nothing (or near-nothing) for a newly-added component, and the rendering/hydration path implements "resolve effective settings" as its own explicit step (needed anyway, now that hydration must also handle enum and value-object conversion, not just a raw spread).

### Precise semantics

- **Defaults are collected** from the constructor's reflected default values, resolved once and cached in the component manifest (§16), never re-reflected per render.
- **Nested `group` fields merge recursively**: an override to one sub-field within a group doesn't require overriding every sub-field — the defaults-then-overrides merge applies at each level of nesting.
- **`repeater` fields are stored in full, not sparsely**, at the list level — there is no coherent single "default item" to be sparse against for an editor-controlled list whose length and order are themselves data. Each individual item's _own_ settings, however, are resolved the same sparse way against that item's own sub-schema defaults.
- **Resetting a field to its default removes the stored override key entirely** — it does not freeze the field at today's default value. A reset field continues to track whatever the constructor's default currently is, including future changes to it. This is a deliberate choice: "reset" means "stop overriding," not "snapshot the current default."
- **A missing key and an explicit `null` are not the same thing.** A genuinely absent key means "inherit the live default." An explicit stored `null` is itself a real override an editor deliberately set (clearing a field that has a non-null default) and does not track future default changes. Any edit made through the settings panel always writes an explicit override for that field — even one that happens to coincidentally equal the current default — because silently omitting a save because "it matches the default anyway" would be non-deterministic and surprising. Only a deliberate "reset to default" UI action removes an override key.
- **Enum defaults serialize as the backed scalar value**, exactly like any stored enum override, re-hydrated to the case at construction time.
- **Value-object defaults serialize the same way any override would**, then hydrate the same way.
- **The admin does not need a complex "inherited" badge in v1** — a field simply displays its effective (resolved) value. The semantics above must be deterministic; the UI treatment of "this happens to be a default" is not required to be visually distinct from "this is an explicit override" in the first release.

---

## 13. Published snapshots must be deterministic

Drafts may be sparse; published revisions may not. **At publish time, the complete resolved effective settings for every declared field are computed and written into the snapshot** — not the sparse stored overrides.

```json
{
  "id": 42,
  "handle": "header-hero",
  "version": 2,
  "settings": {
    "heading": "Summer collection",
    "imageSize": "large",
    "showAction": false,
    "action": null
  },
  "order": 1
}
```

**One uniform algorithm computes "effective settings" everywhere** — draft rendering, published rendering, revision preview, and restore all call the same `currentManifestDefaults() merged with storedValues()` resolution. This single algorithm correctly produces both required behaviours: for a sparse draft, most keys are absent and fall through to current defaults; for an already fully-resolved published snapshot, every relevant key is already present and stored values win, so a later change to the component's own constructor default never silently alters how that historical revision renders. The one true edge case — viewing a historical snapshot that predates a field added since — correctly and unavoidably falls back to today's live default for just that one missing key, which is the best achievable outcome, not a design flaw; restoring that revision immediately "heals" the gap by re-resolving and re-publishing a fresh, complete snapshot.

- **The current published page always renders from the latest snapshot.**
- **Editing the draft after publishing never affects published output** — they are different rows.
- **Restoring an old revision creates a new revision** using the historical resolved settings, per the existing non-destructive restore semantics (`docs/product-redirection-plan.md` §1).
- **Restored historical values are written back as explicit overrides** on the freshly-recreated draft — never reverse-engineered into "well, this one happened to match the default at the time." This is simpler and safer: a restored draft is fully non-sparse, which is fine and expected, since sparseness is an optimization for the common case, not an invariant the system must preserve everywhere.

---

## 14. Component settings versions

```php
public static function settingsVersion(): int
{
    return 2;
}
```

Default `1` if undeclared. Every stored component instance (`page_sections`) and every revision snapshot entry records the version that was current when it was written.

At hydration time: if the stored version matches the currently registered component's version, hydrate normally. If it's older, call `migrateSettings(array $settings, int $fromVersion): array` if the component defines one:

```php
public static function migrateSettings(array $settings, int $fromVersion): array
{
    return match ($fromVersion) {
        1 => [...$settings, 'imageSize' => $settings['image_size'] ?? 'large'],
        default => $settings,
    };
}
```

If no migration path exists for the version gap, resolution follows the same visitor/draft/publish distinction specified for an orphaned enum value (§10): warn and continue for a visitor, a visible warning in draft/preview, and a hard block at publish.

**Migration is in-memory only, on every hydration of an old-versioned record, and never persists a rewrite back to the database merely because a page was viewed or rendered.** A future explicit command could someday bulk-upgrade stored records and bump their recorded version — genuinely future work, not built now. No database-wide migration framework and no Artisan migration generator ship in v1.

### Setting aliases — rejected

A separate `settingAliases()` mechanism (mapping `image_size` → `imageSize`) is **not built**. A rename is exactly the "structural change" `migrateSettings()` already handles — the worked example above literally is a rename. Shipping both would be two overlapping mechanisms for the same problem with no clear precedence between them; one predictable mechanism is preferred.

---

## 15. Conditional fields

```php
->visibleWhen('showAction', true)
->hiddenWhen('showAction', false)
```

`hiddenWhen()` ships as the literal inverse of `visibleWhen()`, not a second mechanism. **`disabledWhen()` does not ship in v1** — no worked example or concrete use case justifies a third field state (visible-but-uneditable) yet.

**Conditional visibility is editor UI behaviour only. It never controls server-side whitelisting, validation, persistence authority, or component constructor types, and this must never change.** Validation and coercion run against the entire declared schema, unconditionally, regardless of a field's current `visibleWhen()` state. A hidden field's value remains validated and is preserved unless an editor explicitly clears it (per §12's null-vs-missing distinction) — an editor toggling a controlling field off and back on should reasonably find their previously-entered value still there, and the moment visibility state were allowed to affect validation, an attacker could bypass a field's coercion by toggling its controlling field before submitting a crafted value. This is a second, deliberate line of defense on top of the existing `PropTamperingRegressionTest`-style discipline this package already has.

---

## 16. Field type decision — closed built-ins for v1

**Option B: retain a closed, built-in field-type representation for v1.** A third-party-extensible registry (Option A) is not built now.

The reasoning: the internal per-type dispatch (coercion, hydration, validation differing between `text`, `select`-with-enum, `link`-as-value-object, `repeater`) needs _some_ polymorphism regardless of whether it's publicly extensible — but that's an internal implementation concern, not a reason to expose a public registration API nobody has asked to use yet. Everything this document requires — typed hydration, attributes, enums, value objects, nested settings, security validation — is fully achievable with `FieldType` remaining a closed, backed PHP enum, exactly as the pre-correction codebase already has it, gaining two cases:

```php
enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Richtext = 'richtext';
    case Number = 'number';   // new
    case Toggle = 'toggle';   // new
    case Select = 'select';   // absorbs "radio"/"segmented" as a presentation-style hint, not a new case
    case Image = 'image';
    case Link = 'link';
    case Repeater = 'repeater';
    case Group = 'group';
}
```

Ten cases, not eleven or twelve — a radio/segmented control is the same underlying data shape as `select` (one value from a labeled set); giving it a separate `FieldType` would duplicate a case for a purely presentational difference in the admin's own field widget, which belongs to a rendering layer (the admin's form rendering), not the schema. **No CSS-class field type ships, or ever will, as a first-party type** — a developer wanting one builds a genuinely custom mechanism for a genuinely unusual case; the package will never encourage it by shipping one.

A third-party `FieldType` registry remains real, plausible future work (`docs/product-redirection-plan.md` §18, Milestone 3) once the closed set has proven itself and real third-party demand exists — not built preemptively because extension sounds attractive in the abstract.

---

## 17. Page Definitions

Unchanged in contract, tightened in one place:

```php
abstract class PageDefinition
{
    public function allowedComponents(): ?array { return null; }
    public function requiredComponents(): array { return []; }
    public function lockedComponents(): array { return []; }
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

Every array is a literal list of concrete component classes. **There is no helper that resolves a category to its members on a Page Definition's behalf, even one resolved once at registration time.** An earlier draft proposed exactly that convenience, reasoning that resolving it once at registration avoided "dynamic" drift; this revision removes it anyway, because the API's _shape_ — "allow this category" — invites a future reader to assume live, ongoing category tracking regardless of when it was actually resolved, which is too easy a misreading for a placement guardrail to risk. A developer who wants "everything in Headers" writes that class list out by hand.

**Required and locked rules are validated before publishing**, not merely suggested in the editor UI. "Locked" covers both "cannot be deleted" and "cannot be reordered" as one axis, not two independent flags — a component that's reorderable but never removable is a rare enough case not to warrant its own concept yet.

**No nested components, slots, regions, `AcceptsChildren`, or `WrapsContent` exist in v1** — composition is a flat, ordered list, rendered inside whatever Blade layout the host application's own route or controller already extends. This is a removal of speculative architecture from an earlier draft, not merely something left unbuilt this release.

---

## 18. Preview architecture

Retains the iframe-based preview using the host application's real frontend environment; tightens the API to one explicit contract.

### One explicit contract, not an ambiguous "layout or view"

```php
Inlay::previewView(
    CollectionPage::class,
    'inlay-previews.collection',
);

Inlay::previewView('inlay-previews.default'); // global default for ordinary standalone pages
```

The named Blade view receives exactly three documented values: `$page`, `$composition`, and `$context`. `$composition` is an **`Htmlable` value object**, never a raw untyped string — preferred deliberately, per this document's own instruction to choose escaping behaviour intentionally rather than default to raw strings. The host places it inside whatever layout and assets it normally uses:

```blade
@extends('layouts.app')

@section('content')
    {{ $composition }}
@endsection
```

`{{ $composition }}` is the recommended usage and is safe here specifically because `$composition` implements `Htmlable` — Blade's `{{ }}` output checks for that interface and calls `->toHtml()` without double-escaping, exactly the same mechanism `RenderPage`'s existing `Htmlable` return already relies on today. `{!! $composition !!}` produces an identical result and remains available, but isn't necessary given the typed value object.

### Preview mechanics, unchanged from an earlier draft

- **Delivery**: a real, separate HTTP response, loaded in an `<iframe>` pointed at a preview route — never HTML injected into the admin's own DOM. This is what makes CSS isolation structural rather than disciplined, and what makes real host assets (Tailwind, Bootstrap, custom JS) load exactly as they would in production.
- **Update mechanism**: debounced iframe reload, not live DOM patching — simpler and more honest than a `postMessage`-based partial-update protocol for v1, and each reload is an independent, fresh page render, exactly like a real navigation.
- **Failure and missing-context display**: in draft/admin context only, a component that throws or is missing required context renders as a visible, clearly-marked inline placeholder in the actual preview output; published rendering strips both to nothing.
- **Context-dependent pages**:

```php
Inlay::previewContext(CollectionPage::class, function (Page $page) {
    return [
        'collection' => Collection::findOrFail($page->context_reference),
    ];
});
```

Called only by the preview/admin path, never by public rendering, and **never persisted into revisions** under any circumstance — its resolved values exist only for the one preview render that used them.

### If the host configures nothing

The admin falls back to a bare, explicitly-labeled unstyled wrapper ("unstyled fallback — configure `Inlay::previewView()` to preview using your real design") rather than silently pretending an unstyled fallback is the real thing.

---

## 19. Domain model changes

| Table            | v1 change                                                                                                                                                                                                                                   |
| ---------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `sites`          | New, one default row, no multi-site UI                                                                                                                                                                                                      |
| `pages`          | Adds `site_id`, `parent_id` (nullable), `path` (nullable), `definition` (nullable string handle)                                                                                                                                            |
| `page_sections`  | `handle` (registered component handle, renamed from `section_type`), `order`, `settings` (renamed from `props` — **sparse editor overrides only**, §12)                                                                                     |
| `page_revisions` | Snapshot entries: `{"id": ..., "handle": ..., "settings": {...fully resolved...}, "order": ..., "version": ...}` — `id` captures the originating `page_sections.id`, `version` captures the component's `settingsVersion()` at publish time |
| `site_settings`  | New, minimal: `name`, `homepage_page_id`, `default_meta_title`, `default_meta_description`                                                                                                                                                  |
| `media`          | New, minimal: `id`, `disk`, `path`, `filename`, `mime_type`, `size`, `alt_text`, `uploaded_by` — backs `ImageValue` (§11)                                                                                                                   |

Not in v1: `navigations`/`navigation_items`, media-backed `site_settings` columns, any Page Definition database table (stays code-only).

---

## 20. Ordered implementation plan and roadmap

### 0.1 — Composition foundation

Everything needed for the full sixteen-step journey (§3), in one release, using the one Blade renderer (§5):

- `FieldType` gains `Toggle`/`Number`; `mapsToClass()` removed.
- `InlayBlock`/`settings()`/`Settings`/`Setting` renames.
- `Inlay::component()`/`components()`/`category()`/`pageDefinition()`.
- Constructor-reflection type/default/nullability inference, with registration-time mismatch validation.
- Optional PHP attribute authoring, merged with fluent declarations per §9's precedence rules.
- Backed-enum options handling; `LinkValue`/`ImageValue` value objects.
- Sparse draft overrides and fully-resolved published snapshots (§12, §13); settings version storage (§14).
- Page Definitions, enforced before publish, with no category shortcut.
- Sites/hierarchy schema, page administration, drag/drop reorder, package-owned admin assets.
- Iframe preview via `previewView()`/`previewContext()`.
- Draft/publish/revisions; public routing and embedded rendering via `renderPage()`.

**A component's own view freely using Livewire, Alpine, or anything else is true from the first commit of this release** — it requires no separate stage, because it was never a package-level rendering concern.

**Acceptance:** the full sixteen-step journey works end to end, demonstrated with at least one component whose own view contains a genuine, hydrating Livewire component, requiring zero package-level Livewire awareness to do so.

### 0.2 — Authoring maturity

Only what wasn't already completed cleanly in 0.1: the `migrateSettings()` hook, if not built alongside version storage; the compiled component-definition manifest/cache (§21), if reflection-per-request proves costly sooner than expected; improved registration-time diagnostics; media-record improvements; richer documentation.

### 1.0 — stable

The complete Blade-component composition workflow is stable. Blade components may internally use Livewire, Alpine, JavaScript, or any other host-owned frontend behaviour. No claim of a directly-registered outer Livewire component. No Vue/React/Inertia adapter claim. Historical revisions render deterministically regardless of later default changes. Authoring APIs (constructor inference, attributes, enums, value objects, versions) are documented and stable.

**No direct Livewire rendering adapter is required before 1.0**, because none is required at all — it was never a real gap.

---

## 21. Reflection and caching strategy

- **Development**: constructor and attribute reflection runs at registration time (when `Inlay::component()` is called), which is cheap enough at development scale to re-run freely, favouring DX over micro-optimizing a cost nobody will notice.
- **Production**: reflection results are written once into a compiled component manifest (`php artisan inlay:cache`, hooked into `php artisan optimize`), consulted on every subsequent request without further reflection. `APP_ENV=production` with no manifest present fails loudly rather than silently falling back to a live scan.
- **What the manifest contains**: component identity (handle, display metadata, category, icon), the resolved `FieldType` for every setting, each setting's derived PHP type, nullability, default value (already serialized to its stored representation), enum or value-object class where applicable, and the component's `settingsVersion()`.
- **What the manifest never contains**: an executable closure. Any schema behaviour that can't be safely serialized (a closure-based custom validation rule, for instance) is resolved by referencing the owning class and re-invoking it live, not by attempting to cache the closure itself.
- **Guiding constraint**: the manifest exists to remove per-request reflection cost, not to become a second, more complex system than the feature it optimizes — if a piece of metadata is cheap enough to resolve live in production without a measurable cost, it does not need to be cached merely for architectural symmetry.

---

## 22. Explicitly deferred features

- Arbitrary HTML/CSS editing
- Tailwind-specific settings
- A package-owned frontend design system
- Generated frontend markup
- Visual creation of new components
- No-code business logic
- **A directly-registrable outer Livewire, Vue, React, or Inertia component, and any adapter for one** — removed from v1 architecture, not merely unbuilt
- Nested components, slots, regions, `AcceptsChildren`, `WrapsContent` — removed from v1 architecture entirely
- A third-party-extensible `FieldType` registry
- A category-to-Page-Definition dynamic-membership shortcut
- A setting-alias mechanism alongside versioned migration
- `disabledWhen()` conditional field state
- A database-wide settings-migration Artisan command
- Reusable global components
- Navigation management
- A full media library
- Multi-site UI
- Hosted SaaS functionality
- Themes
- A marketplace
- Signed external preview links

---

## 23. Testing matrix

| Area                             | What must be proven                                                                                                                                                                                                                                                                                                    |
| -------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Constructor inference            | Default, type, and nullability correctly derived; a schema field with no matching parameter fails at registration; incompatible field/PHP types fail at registration; a required non-nullable parameter with no default and no explicit override fails at registration; a duplicate setting name fails at registration |
| Attributes                       | An attribute-only component works; a fluent-only component works; a component using both merges by name with fluent winning; a type conflict between the two fails at registration                                                                                                                                     |
| Backed enums                     | Correct persistence of the scalar value; correct hydration to the case; an invalid/orphaned stored value falls back to the constructor default rather than erroring                                                                                                                                                    |
| Typed value objects              | `LinkValue`/`ImageValue` serialize and hydrate correctly; null handling; URL-scheme validation on `LinkValue` unchanged in behavior from today                                                                                                                                                                         |
| Sparse overrides                 | A newly-added field on an existing component resolves to its default with no stored override; an explicit override persists and wins; resetting removes the override key; an explicit `null` override is distinct from a missing key                                                                                   |
| Resolved snapshots               | Publishing writes fully-resolved settings for every field; changing a component's constructor default afterward does not alter an already-published revision's rendered output; restoring a revision produces deterministic settings and writes them back as explicit overrides                                        |
| Settings versions                | Version is recorded on both drafts and snapshots; a version mismatch with a `migrateSettings()` hook resolves correctly, in memory only, without persisting a rewrite; a mismatch with no hook produces a visible warning rather than a silent or fatal failure                                                        |
| The one renderer                 | `BladeComponentRenderer` output is unchanged in behavior from today's `SectionRenderer` for existing fixtures (characterization); a component whose own view contains `<livewire:...>` renders and hydrates correctly with no package-level special-casing                                                             |
| Page Definitions                 | `allowedComponents()`/`requiredComponents()`/`lockedComponents()`/`defaultComponents()` enforced before publish; no API exists for allowing a category dynamically                                                                                                                                                     |
| `visibleWhen()`/`hiddenWhen()`   | UI visibility toggles correctly; a hidden field's value is still validated, coerced, and persisted regardless of visibility state (direct regression test in the spirit of `PropTamperingRegressionTest`)                                                                                                              |
| Preview                          | `previewView()` renders inside the host-configured view; `$composition` is a genuine `Htmlable`; draft vs. published divergence; render-failure and missing-context placeholders appear only in admin context; `previewContext()` resolves and is never persisted                                                      |
| Frontend neutrality (end-to-end) | A test host app using plain CSS (no Tailwind) renders a composed page with zero admin-originated CSS in the output                                                                                                                                                                                                     |
| Install command                  | Runs cleanly under Testbench; prints the `previewView()` next step                                                                                                                                                                                                                                                     |

---

## 24. Stable v1.0 definition of done

- The full sixteen-step journey (§3) works end to end, including a component whose own view contains a genuine, hydrating Livewire component.
- Frontend neutrality is verified, not asserted, against a plain-CSS test host application.
- Page Definition guardrails are enforced before publish and covered by tests.
- The effective-settings model (§12–§14) behaves exactly as specified, including the security-boundary guarantees around `visibleWhen()`.
- No documentation, code comment, or admin UI copy claims a directly-registered outer Livewire, Vue, React, or Inertia component is supported.
- Package admin assets are compiled and self-hosted; no CDN dependency anywhere.
- The full testing matrix (§23) passes.

---

## 25. Decisions requiring sign-off

1. **Removing `LivewireComponentRenderer`, auto-detection, and the staged Blade/Livewire pre-1.0 track**, collapsing to a single `0.1` release. This is a correction of the previous draft of this very document, not an external requirement — confirm the reasoning in §5 is sound before treating it as settled.
2. **Reversing the `FieldType` registry decision** back to a closed built-in enum for v1 (§16).
3. **The full authoring model** — constructor inference, attributes, enums, value objects, sparse overrides, resolved snapshots, settings versions (§8–§14) — is now the largest single piece of new architecture in this project. Confirm the scope is acceptable for a first release, given how much smaller the rendering side of the plan has become in exchange.
4. **`previewView()` replaces `previewLayout()`** as one explicit contract rather than an ambiguous layout-or-view API (§18).
5. **No setting-alias mechanism, no `disabledWhen()`, no category-to-Page-Definition shortcut** — three separate deliberate omissions (§14, §15, §17), each removing a plausible convenience in favour of one predictable mechanism or a stricter, safer API shape.
6. Everything carried over from the redirection plan's own sign-off list still stands.

---

## 26. Required final recommendations

**Is `InlayBlock` the final public contract name?**
Yes.

**Is `settings()` the final public declaration method?**
Yes, returning a `Settings` object built via `Settings::make()`.

**Should constructor defaults be authoritative over schema defaults?**
Yes, always — `settings()`/attributes declare editor-facing behaviour only; type and default come from the constructor.

**Should attributes and fluent definitions both ship in v1?**
Yes, with fluent (`settings()`) as the preferred style for complex schemas and attributes as an optional convenience for simple ones.

**What are the exact merge and precedence rules?**
Merge by setting name across both styles; fluent wins for shared metadata; constructor type/default always wins regardless of authoring style; a type conflict between the two fails at registration rather than resolving silently.

**Should backed enums ship as first-class setting values?**
Yes — persisted as their scalar value, hydrated to the case, validated against real cases, falling back to the constructor default on an orphaned value.

**Which typed value objects should ship in v1?**
`LinkValue` and `ImageValue`. No value objects for presentation concepts.

**Should drafts store sparse overrides?**
Yes — this is the load-bearing decision in this document (§12).

**Should published revisions store complete resolved settings?**
Yes, always, computed at publish time (§13).

**Should Component settings versions be stored from day one?**
Yes.

**Should a migration hook ship in 0.1, 0.2, or after 1.0?**
0.1 if it can be built alongside version storage without expanding scope meaningfully; 0.2 otherwise. Not later than that — a version with no migration path at all for its first several releases would make the version field decorative rather than useful.

**Should setting aliases be omitted in favour of versioned migrations?**
Yes, decisively (§14).

**Does the `FieldType` registry genuinely belong in v1?**
No (§16) — a closed, built-in set is sufficient for everything v1 needs; third-party extensibility is real future work, deferred.

**What is the final preview-view API?**
`Inlay::previewView(PageDefinitionClass::class, 'view.name')` for a context-bound page, and a bare `Inlay::previewView('view.name')` global default for ordinary pages — one explicit contract, receiving `$page`, `$composition` (`Htmlable`), and `$context`.

**What is the smallest implementation phase that now produces visible product progress?**
The `FieldType`/naming corrections, the authoring model and registry (constructor inference, attributes, enums, value objects, sparse overrides), and the corrected single Blade renderer, followed immediately by admin routes, page index/creation/settings with the Page Definition picker, and public/embedded rendering — everything in 0.1 through the point where a page composed of components (including one using nested Livewire) can be created, published, and viewed live. This is smaller than either prior draft's "first slice," because there is no longer a second renderer or adapter to build before that milestone is reached.
