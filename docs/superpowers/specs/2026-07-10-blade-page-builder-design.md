# blade-page-builder — Design Spec

Date: 2026-07-10
Status: Approved for planning

## Context

Pixel wants to offer clients a drag-and-drop page building experience comparable to
Elementor (WordPress) or Shopify's theme editor, but built on the stack Pixel already
uses everywhere: Laravel, Blade, Tailwind, Livewire.

This is the first of three related sub-projects, scoped deliberately narrow:

1. **This spec: `blade-page-builder`** — the reusable Composer package. The drag-and-drop
   engine, the editable-section model, persistence, and rendering. No sections ship in
   the package itself.
2. **pixel-cms (future spec)** — the multi-tenant SaaS app (`cms.wearepixel.com.au`) that
   will consume this package: client accounts, billing, custom domains, onboarding.
3. **Retrofit into existing apps (future spec)** — installing the package into apps such
   as `nourishd` that weren't originally built with it.

Sub-projects 2 and 3 are out of scope here and will each get their own
brainstorm → spec → plan cycle once this package is proven.

### Prior art considered

Pixel already runs a **Foundry** ecosystem (`foundry`, `foundry-ecosystem`) — a
"copy files into the target app via a manifest, the app then owns and can diverge from
them" scaffolding model (`foundry.json`, per-stack `files/` variants, `apply`/`after`
scripts). That model was explicitly considered and rejected for this project: the
builder needs to stay a centrally maintained, versioned dependency that ships updates
via `composer update`, not app-owned code that immediately diverges per install.

## Decisions

- **Distribution**: a true Composer package (`wearepixel/blade-page-builder`), not a
  Foundry-style scaffold. Service provider, published config/views/migrations.
- **Editor stack**: plain Livewire + Alpine. No hard dependency on Filament — several
  target apps (e.g. nourishd, on Livewire 3) don't run Filament, and portability across
  Pixel's existing client apps matters more than the speed-up Filament's form builder
  would give us.
- **Repo layout**: separate repo from day one
  (`/Users/joel/Code/blade-page-builder`, sibling to `pixel-cms`), wired into consuming
  apps via a Composer path repository during development and a tagged version once
  stable. Matches how `laravel-cart` / `laravel-comments` are already run.
- **Schema definition**: class-based section components with a static `schema()`
  method, not a separate schema file or constructor attributes. Keeps the field
  declarations next to the component they control, with full PHP autocompletion.
- **Content storage**: relational `pages` + `page_sections` tables (not a single JSON
  blob, not fully polymorphic in v1 — see Data Model).
- **Draft/publish**: v1 ships draft + publish, not live-only editing. Clients editing a
  public-facing page need a safe "preview then publish" flow.
- **Version history**: whole-page snapshot revisions (not per-field diffs). Every
  publish (and restore) writes a full snapshot of the section tree to an append-only
  `page_revisions` log.
- **Reorder mechanic**: SortableJS bound through Alpine/Livewire, matching the drag
  library already used across the Laravel admin-panel ecosystem (Filament, Nova).

## Architecture

New repo `wearepixel/blade-page-builder`, a standard Laravel package:

```
blade-page-builder/
├── composer.json
├── config/blade-page-builder.php
├── database/migrations/
├── src/
│   ├── BladePageBuilderServiceProvider.php
│   ├── Schema/                  # Schema, field type builders
│   ├── Models/                  # Page, PageSection, PageRevision
│   ├── Livewire/PageEditor.php
│   └── Contracts/Sectionable.php
└── resources/views/
    ├── livewire/page-editor.blade.php
    ├── components/render.blade.php   # <x-page-builder::render :page="$page" />
    └── fields/                       # one partial per field type
```

The package supplies **the engine only**. Two things the consuming app supplies:

- **Sections** — Blade class components living in the consuming app
  (e.g. `app/Sections/HeroSplit.php`), each implementing `Sectionable` and declaring a
  `schema()`.
- **Section Registry** — a config array (published by the package) mapping a slug to a
  section class, so the editor knows what's available to drag in.

`composer require wearepixel/blade-page-builder`, then
`php artisan vendor:publish --tag=blade-page-builder-config` and
`php artisan migrate`.

## Section Component Model

```php
declare(strict_types=1);

namespace App\Sections;

use WearePixel\BladePageBuilder\Contracts\Sectionable;
use WearePixel\BladePageBuilder\Schema\Schema;

class HeroSplit extends \Illuminate\View\Component implements Sectionable
{
    public function __construct(
        public string $heading = '',
        public string $body = '',
        public ?string $image = null,
        public string $layout = 'image-right',
    ) {}

    public static function schema(): Schema
    {
        return Schema::make()
            ->text('heading', label: 'Heading')
            ->richtext('body', label: 'Body copy')
            ->image('image', label: 'Photo')
            ->select('layout', label: 'Layout', options: [
                'image-left'  => 'Image on left',
                'image-right' => 'Image on right',
            ])->mapsToClass(
                fn (string $value) => $value === 'image-left' ? 'flex-row-reverse' : 'flex-row'
            );
    }

    public function render(): \Illuminate\View\View
    {
        return view('sections.hero-split');
    }
}
```

Constructor properties with no matching `schema()` entry are non-editable — this is how
a developer marks something as fixed by design versus client-editable, per the original
requirement ("there will always be times where some things aren't changeable").

The `mapsToClass()` mechanic generalizes the "swap image/text side via dropdown"
requirement: any `select` field can map its chosen value to a Tailwind class string
instead of raw content, which the Blade view consumes directly.

### v1 field types

`text`, `textarea`, `richtext` (WYSIWYG), `image`, `select` (with optional
`mapsToClass`), `link` (label + URL + open-in-new-tab), `repeater` (nested list of
sub-fields — testimonials, FAQ items), `group` (nested non-repeating field set — e.g. a
CTA button cluster of label/url/style).

## Data Model

- **`pages`** — `id`, `title`, `slug`, `status` (draft/published), `meta_title`,
  `meta_description`, timestamps.
- **`page_sections`** — `id`, `page_id`, `section_type` (registry slug), `order`,
  `props` (JSON), timestamps. Represents the live editing surface — always "current
  draft state". There is one tree per page, not two parallel draft/published trees to
  keep in sync.
- **`page_revisions`** — `id`, `page_id`, `snapshot` (JSON — full ordered section tree
  at that point), `created_by`, `created_at`, optional `label`.

**Publish** = serialize current `page_sections` into a new `page_revisions` row, flip
`pages.status` to published. **Restore** = pick a revision, overwrite the live
`page_sections` rows from its snapshot — restoring itself creates an implicit new
revision, so it's never destructive. Version history is an append-only log; nothing is
ever deleted from `page_revisions`.

## Editor UI & Rendering

A single Livewire component, `PageEditor`, mounted by the consuming app at whatever
admin route it chooses (e.g. `/admin/pages/{page}/edit`). Renders:

- Section list (left panel) — order-editable via SortableJS/Alpine, calling
  `updateOrder($orderedIds)` on drop.
- "Add Section" picker, pulling from the registered section list.
- Field panel (right) — clicking a section renders its `schema()` as real Livewire
  inputs, one Blade partial per field type, `wire:model`-bound into that section's
  `props`.
- Live preview pane — renders the actual section Blade views with current draft props.
  No separate preview-vs-production renderer; same code path, fed draft or published
  props.

**Live rendering**: `<x-page-builder::render :page="$page" />` dropped into the app's
page template loops the page's sections in publish/draft order and does
`<x-dynamic-component :component="$section->componentName()" ...$section->props />`.

**Auth**: left entirely to the consuming app. The package exposes a Livewire component
and expects to be wrapped in whatever middleware/layout the app's own admin already
uses — no opinion imposed about how admins authenticate.

## Testing

Pest feature tests per Pixel's standard rules:

- Schema-to-form rendering for each field type
- Drag-reorder persists `order` correctly
- Publish creates a revision snapshot; restore overwrites `page_sections` correctly
- `mapsToClass` select fields output the correct Tailwind classes
- Non-schema constructor props are never editable from the UI

`declare(strict_types=1)`, typed properties/returns, no `dd()`/`dump()`/`ray()`. Run the
`security-reviewer` agent after implementation — this package handles rich text and
image upload input directly.

## Explicitly Out of Scope

Each of these becomes its own brainstorm → spec cycle later:

- pixel-cms as a SaaS product — tenancy model, billing, client onboarding, custom
  domain routing
- Retrofitting the package into nourishd or other existing apps
- Image storage/CDN specifics — v1 assumes whatever `Storage` disk the consuming app
  already has configured
- Roles/permissions for _who_ can edit within an app — package assumes the app's own
  admin auth wraps it
- A theme/global-styles system (shared colors/fonts across sections) — sections are
  self-contained for v1
