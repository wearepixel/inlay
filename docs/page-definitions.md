# Page Definitions

By default, any Page may contain any registered Block, in any order, with no seeded starting content. That's the right default for most pages - but some pages need to guarantee their own structure: a product Collection Page that must always open with a hero and a product grid an editor can't remove; a Landing Page that only ever allows a small, curated set of promotional blocks. A **Page Definition** is how you declare that structure once, in PHP, and have Inlay enforce it everywhere - the editor's picker, the block tree's own guardrails, and the publish gate.

This is the package's answer to "how do I use Inlay safely for a page whose layout is business-critical" - ecommerce, authenticated app surfaces, anything where an editor accidentally deleting the wrong block would actually break something.

## Block vs. PageSection vs. Page Definition

Three different things share this vocabulary; keep them distinct:

- A **Block** is a reusable, developer-authored Blade component - `Hero`, `ProductCollection` - registered once, usable on any Page.
- A **PageSection** is one _placed instance_ of a Block on a specific Page, with its own settings and position. A Page's composition is an ordered list of these.
- A **Page Definition** is neither of the above - it renders nothing, is not itself a Block, and produces no output. It's pure policy: which Blocks a _kind_ of Page may contain, and what a new Page of that kind starts with.

A Page Definition is registered once and referenced by every Page of that kind via a stable handle - never a serialized copy of its rules. The PHP class is always the single source of truth; nothing about `allowedBlocks()`/`requiredBlocks()`/`lockedBlocks()`/`defaultBlocks()` is ever written to the database.

## Registering a Page Definition

```php
namespace App\Inlay\Pages;

use WeArePixel\Inlay\Pages\PageDefinition;
use WeArePixel\Inlay\Support\BlockHandle;

final class CollectionPage extends PageDefinition
{
    public function allowedBlocks(): array
    {
        return [
            CampaignHero::class,
            ProductCollection::class,
            EditorialContent::class,
            BlockHandle::make('promotion-banner'), // a single-file block
        ];
    }

    public function requiredBlocks(): array
    {
        return [ProductCollection::class];
    }

    public function lockedBlocks(): array
    {
        return [ProductCollection::class];
    }

    public function defaultBlocks(): array
    {
        return [CampaignHero::class, ProductCollection::class];
    }
}
```

```php
// routes/web.php, or anywhere run once during boot - after your blocks are registered
use WeArePixel\Inlay\Facades\Inlay;

Inlay::pageDefinition(CollectionPage::class);
// or register several at once:
Inlay::pageDefinitions([CollectionPage::class, LandingPage::class]);
```

Register Page Definitions **after** the Blocks they reference - every reference is validated immediately against the currently-registered Blocks, with a clear exception naming the definition and the missing Block if it isn't found yet.

You never have to register anything to use Inlay without this feature at all: a built-in, unrestricted **default** Page Definition always exists, and every Page created before this feature (or with no explicit type chosen) uses it - every registered Block allowed, nothing required, locked, or seeded.

## Class-based and single-file Block references

Every Block reference in a Page Definition is either a class-string (for a class-based Block) or a `BlockHandle` (for a single-file Block, which has no class to reference):

```php
public function allowedBlocks(): array
{
    return [
        Hero::class,                          // class-based
        BlockHandle::make('promotion-banner'), // single-file
    ];
}
```

A bare, untyped string is never accepted here - `BlockHandle::make(...)` exists specifically so a typo or a stringly-typed reference fails immediately and clearly, rather than silently doing nothing. Every reference is normalised into the Block's own stable registered handle (the same handle stored on a `PageSection`) as soon as the Page Definition is registered, and validated then: an unregistered Block, a Page Definition handle already claimed by another definition, or a locked Block that isn't also required, all fail loudly with a message naming the definition and the problem - never silently at Page-creation or publish time.

## Metadata

```php
use WeArePixel\Inlay\Attributes\PageDefinitionMeta;

#[PageDefinitionMeta(
    label: 'Collection Page',
    description: 'A product collection with a curated hero and grid.',
)]
final class CollectionPage extends PageDefinition
{
    // ...
}
```

`#[PageDefinitionMeta]` mirrors `#[BlockMeta]` in spirit: entirely optional. With none given, the handle and label are derived from the class name (`CollectionPage` → handle `collection-page`, label "Collection Page"), the same convention-over-configuration fallback Blocks already use for a missing `#[BlockMeta]`.

## Rule semantics

### Allowed

`allowedBlocks()` returning `null` (the default, inherited if you don't override it) means every registered Block is allowed - an unrestricted Page Definition. An explicit array restricts a Page of this kind to exactly those Blocks. A Block's picker **category** is presentation only, never a permission boundary - grouping "Headers" together in the picker says nothing about which specific Blocks a given Page Definition allows.

A Block listed in `requiredBlocks()`, `lockedBlocks()`, or `defaultBlocks()` is **always also allowed**, whether or not you separately list it in `allowedBlocks()` too - you don't have to repeat yourself, though the worked example above does, for clarity at a glance.

### Required

A required Block must exist at least once before the Page can be published. In the editor, the **final** remaining instance of a required Block can't be deleted (deleting one of several instances is fine, as long as at least one remains) - this is enforced as a courtesy in the UI, and independently, again, at the publish gate.

### Locked

A locked Block instance can be neither deleted nor reordered. **Every** placed instance whose handle appears in `lockedBlocks()` is locked - this is a simple, predictable type-level rule, not per-instance metadata; there is no way to lock one specific instance of a repeatable Block while leaving another instance of the same Block movable.

A locked Block must always also be required - listing one as locked but not required is rejected at registration time with a clear message, rather than silently normalised into one or the other. Duplicating a locked instance is allowed; the copy is locked too, by the same type-level rule.

### Default

`defaultBlocks()` are seeded, in the declared order, the moment a new Page of that Page Definition is created - with sparse settings, resolved from each Block's own current defaults, exactly as if you'd added them by hand from the picker. Repeating the same handle is fine (two `CampaignHero::class` entries seed two instances). Default Blocks do not automatically become locked - only `lockedBlocks()` does that.

## Page creation

Page creation gains a "Page type" selector once more than one Page Definition is registered - with only the built-in default, the selector is hidden entirely, so a project that never needs this feature sees no extra ceremony. Choosing a type seeds its default Blocks immediately.

**A Page's Page Definition is fixed at creation and cannot be changed afterward.** This is a deliberate simplification for this release: validating every existing PageSection against a _different_ definition's rules, showing exactly what would become invalid, and requiring confirmation, is a real feature in its own right - safely reassigning a Page's type later is not implemented yet. If you need a different structure, create a new Page with the right type.

## Editor guardrails

The block picker shows only Blocks the current Page's definition allows, with categories, search, thumbnails, and keyboard navigation all still intact - filtering narrows the list, it doesn't flatten the picker's own structure. A previously "recently added" Block that the current Page's definition doesn't allow doesn't appear in the picker's recents row either.

In the block tree, a required Block carries a calm "Required" indicator; a locked one carries "Locked" and has no delete control at all (disabled, not merely hidden) and no drag handle or keyboard reorder shortcut.

**None of this is a security boundary by itself.** Every one of `addSection()`, `deleteSection()`, `duplicateSection()`, and reordering is independently re-checked server-side, rejecting a disallowed Block, a locked deletion, the final required instance, or a reorder that would move a locked Block - even if a crafted request bypasses the UI entirely.

## Publishing validation

One authoritative validator (`WeArePixel\Inlay\Pages\PageDefinitionValidator`) is used identically by the editor's own standing status banner and by the publish gate itself (`PublishPage`) - never two independent implementations of the same rules. It reports structured violations:

- a Block on the page the definition doesn't allow;
- a required Block missing entirely;
- the Page's own definition no longer being registered (its handle was renamed or removed);
- a Block on the page that's no longer registered at all.

Publishing throws (`PageDefinitionViolationException`, carrying the full violation list) whenever any of these are true - the editor shows the same messages as a calm, standing banner before you even click Publish, in plain language, never a raw class name or handle. **A previously published revision is never invalidated retroactively** - if a developer later removes a Block from `allowedBlocks()`, pages already published with it keep rendering exactly as before; only the _next_ publish attempt is blocked until the draft is corrected.

## Revisions and restore

A published revision snapshots Block handles, settings, and order - never a Page Definition's rules - plus, for audit context only, the definition's own handle at the moment of publishing. Restoring an older revision always replaces the live draft with that revision's content; whether it also immediately re-publishes depends on whether the restored content satisfies the Page's **current** definition (which may have changed since - a developer can tighten a Page Definition's rules between deploys just like any other code change):

- if it's still valid, restoring is itself a publish, exactly as for a Page with no Page Definition at all;
- if it's no longer valid (a since-added required Block is missing, a since-locked Block's position no longer matches, or anything else the validator would flag), the content lands in the draft only, and publishing stays blocked until it's corrected - restoring never silently republishes something that would now be invalid, and never switches the Page back to whatever definition applied when that revision was published.

## What happens when a Page Definition evolves

The PHP class is always authoritative and always live - there's no stored, versioned copy of its rules to migrate. A few concrete consequences:

- Removing a Block from `allowedBlocks()` doesn't touch already-published output; it blocks the _next_ publish of any Page containing it, with a clear violation.
- Renaming or deleting a Page Definition class (so its handle no longer resolves) leaves existing Pages pointing at a handle nothing answers to - the editor shows a clear, developer-facing message, the block picker shows nothing (there are no rules left to allow anything by), and publishing is blocked, but the Page's public rendering of its **last published revision** is unaffected.
- Locking a previously-unlocked Block affects new reorders and deletions immediately, and is checked again on the next restore of an older revision (see above) - it does not retroactively "fix" a draft that's already in a since-disallowed state; that still needs a normal edit.

## Scope

Not implemented in this release, deliberately:

- maximum/minimum instance counts for a Block;
- locking or requiring one specific _instance_ rather than every instance of a Block;
- regions/slots, or nested Blocks;
- conditional allow-lists (e.g. "allowed only if another Block is present");
- role-specific Page Definitions;
- editor-built Page Definitions, or Page Definition rows in the database;
- reassigning a Page's Page Definition after creation;
- visual templates as a separate concept from a Page Definition.
