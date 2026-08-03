# Manual review checklist

A precise checklist for manually reviewing the admin in a real browser before a release. This is a QA pass, not a redesign session - if something here reveals a defect that makes the workflow unusable or misleading, fix it; anything that's merely rough or could be nicer belongs in a separate polish backlog, not folded into this pass.

Test with at least two registered blocks (one with several settings including a repeater/group and a conditional field, one plain) and at least 25 pages in the index, so pagination and "many blocks" scenarios are real rather than theoretical.

## Desktop layout

- [ ] Top nav: brand and "Pages" link don't collide with the page's own topbar actions at common widths (1280px, 1440px, 1920px).
- [ ] Page index: table columns align, status/Homepage badges are legible, the All/Draft/Published tabs reflect which filter is active, search box and "New page" button don't collide.
- [ ] Editor: the two-pane layout (contextual panel / preview) keeps sensible proportions; the preview stays dominant (~70-75% of the width) regardless of how many settings the selected block has.
- [ ] Dragging the divider between the contextual panel and the preview resizes it smoothly, clamps at sensible min/max widths, and the chosen width survives a page reload.
- [ ] The collapse toggle (hover the divider) hides the contextual panel to a true 0px - not just visually, check there's no residual padding/border - and the preview claims the freed width.
- [ ] Preview toolbar shows the page's resolved public URL (or "Not published"), and the refresh icon actually reloads the iframe.
- [ ] Block picker drawer doesn't exceed the viewport, and its own internal scroll (not the page's) activates when there are many blocks.

## Narrow viewport layout

- [ ] Page index remains usable down to a typical tablet width; the table doesn't overflow the viewport uncontrollably.
- [ ] Editor's contextual panel becomes proportionally wider at tablet widths (the resize handle is hidden here, by design) and switches to one full-width panel at a time (via the Edit/Preview segmented control) at phone widths, with no horizontal overflow.
- [ ] Block picker drawer doesn't exceed the viewport width/height on a narrow screen.

## Empty states

- [ ] Page index with zero pages: message is clear, "New page" is still reachable.
- [ ] Page index with a status/search filter matching nothing: distinguishes "no pages match this filter" from "no pages exist yet."
- [ ] Editor with zero blocks: tree message is clear, "+ Add" is still reachable, and the preview pane itself shows a quiet "Nothing here yet" mark rather than looking broken or blank.
- [ ] Settings inspector with nothing selected: defaults to the block tree rather than showing an empty panel.
- [ ] Block picker with zero registered blocks: message is clear rather than a blank panel.
- [ ] Version history with zero revisions: message is clear rather than a blank list.
- [ ] Image field with nothing uploaded: shows the dashed dropzone (not a bare unstyled file input), and it's clickable and keyboard-reachable.

## Loading states

- [ ] Typing in a debounced text field doesn't visibly flicker or reset the field while the request is in flight.
- [ ] Clicking Publish gives some visible acknowledgment before/while the request completes, not just a silent pause.
- [ ] Switching selected blocks while a previous save is still in flight doesn't show the wrong block's data momentarily.

## Validation errors

- [ ] Page creation: submitting an empty title shows a clear error, doesn't lose the path value already entered.
- [ ] Page creation: a duplicate path shows a clear error identifying the conflict, doesn't silently fail.
- [ ] Image upload: a non-image file is rejected with a clear message, not a silent no-op.

## Block-picker search

- [ ] Typing a term that matches nothing shows an understandable "no results" state, not an empty white area indistinguishable from a bug.
- [ ] Search matches against both a block's label and its description.
- [ ] Clearing the search restores the full, categorized list without needing to reopen the picker.

## Category grouping

- [ ] Blocks with no `#[BlockMeta]` land in a clearly-labeled "Uncategorised" group, not silently hidden.
- [ ] A category with only one block still gets its own heading, not merged into another.
- [ ] Category order is stable across repeated opens of the picker (not randomly reshuffling).

## Long block names / long field labels

- [ ] A block label long enough to wrap doesn't break the picker card's layout or overlap the description.
- [ ] A field label longer than the inspector column's width wraps rather than overflowing into the preview column.
- [ ] A long option label in a select field doesn't break the dropdown's layout.

## Large numbers of blocks

- [ ] A page with 30+ blocks: the tree list scrolls independently and remains responsive to select/reorder.
- [ ] A registry with 30+ blocks: the picker's category grouping and search both remain fast and legible.
- [ ] Publishing a page with many blocks doesn't visibly hang the UI beyond a reasonable, acknowledged wait.

## Iframe failure

- [ ] A block that throws during render: confirm the existing per-block isolation holds (the rest of the page still renders) and check what, if anything, appears in place of the failing block in the _admin_ preview versus a real published page.
- [ ] A completely broken preview view (e.g. references an undefined variable): confirm what the iframe actually shows - a Laravel error page, a blank frame, or something else - and whether that's acceptable for this release.

## Missing preview view

- [ ] With no `Inlay::previewView()` configured, the preview iframe shows the labeled "unstyled fallback" wrapper, not a blank frame or a raw exception.
- [ ] The fallback message is legible and clearly attributes the missing configuration, not just "something is wrong."

## Render failure placeholders

- [ ] Confirm current behaviour for a section referencing an unregistered handle (deleted from `config('inlay.blocks')` after being added to a page) - it should be skipped, not take down the rest of the page.
- [ ] Confirm current behaviour for a section whose stored settings can no longer construct its block (e.g. a required constructor argument's type changed) - same expectation.
- [ ] Note whether either case surfaces _any_ indication to the person editing that something was silently skipped, versus only appearing in logs - this is a known gap (see README's "Current limitations"), not something to fix in this pass.

## Authentication behaviour

- [ ] With `config('inlay.admin.middleware')` set to include `auth`, an unauthenticated visit to any admin URL is actually blocked, not just the page index.
- [ ] A logged-in, authorized user can complete the full workflow without being unexpectedly challenged again mid-session.
- [ ] Confirm `config('inlay.admin.middleware')` is documented as insecure-by-default in the README/installation guide, and that this hasn't drifted from what's actually shipped.

## Draft/published divergence

- [ ] Editing a published page's draft does not change what a public visit currently renders.
- [ ] Publishing updates the public output immediately, without a cache or delay making it look broken.
- [ ] A page that has never been published renders nothing on a public route - confirm this is a clean empty response, not an error.

## Revision restore

- [ ] Restoring an older revision visibly updates both the draft (inspector shows the restored values) and the public output (it publishes immediately).
- [ ] The restored revision's label ("Restored from revision #N") is accurate and appears in the history list afterward.
- [ ] Restoring doesn't lose the ability to restore a _different_ older revision afterward (history isn't truncated by the act of restoring).

## Keyboard focus

- [ ] Opening the block picker moves focus into its search field.
- [ ] Closing the picker (via the close/overlay click or after adding a block) returns focus somewhere sensible, not to `<body>`.
- [ ] Every actionable icon-only button (duplicate, delete, refresh, collapse toggle) is reachable and operable via keyboard, not just mouse hover + click.
- [ ] Pressing **?** while focus is in a text field types a literal "?" rather than opening the shortcuts overlay; pressing it anywhere else in the editor opens it, and Escape or an outside click closes it.

## Tab order

- [ ] Tabbing through the page index reaches search, then each page's edit/delete actions, in visual order.
- [ ] Tabbing through the editor reaches the block tree, its actions, the inspector's fields, and the top-bar buttons in a sensible order - not jumping unpredictably between panes.
- [ ] Tabbing through the block picker stays within the drawer while it's open (no focus escaping to page content behind it).

## Contrast and accessibility

- [ ] Body text, labels, and the "Uncategorised"/eyebrow small-caps text meet reasonable contrast against their background.
- [ ] Disabled/placeholder states (e.g. a disabled pagination link) are visually distinguishable from active ones without relying on color alone.
- [ ] Status badges (draft/published) are distinguishable without relying on color alone (e.g. also differ in text).
- [ ] Icon-only buttons (duplicate, delete, drag handle, refresh, collapse toggle) have an accessible name (`title`/`aria-label`), not just a visual glyph - every icon in the admin is now an inline SVG (`resources/views/components/icon.blade.php`), so check none of them regressed to a raw HTML entity.

## Recording findings

Log anything found here as a separate, itemized polish/defect list - do not silently fix cosmetic issues mid-checklist and do not silently expand this checklist's scope into a redesign. A finding here is "here's what's wrong and where," not "here's the new design."
