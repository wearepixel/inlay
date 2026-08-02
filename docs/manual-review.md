# Manual review checklist

A precise checklist for manually reviewing the admin in a real browser before a release. This is a QA pass, not a redesign session - if something here reveals a defect that makes the workflow unusable or misleading, fix it; anything that's merely rough or could be nicer belongs in a separate polish backlog, not folded into this pass.

Test with at least two registered components (one with several settings including a repeater/group and a conditional field, one plain) and at least 25 pages in the index, so pagination and "many components" scenarios are real rather than theoretical.

## Desktop layout

- [ ] Page index: table columns align, status badges are legible, search box and "New page" button don't collide at common widths (1280px, 1440px, 1920px).
- [ ] Editor: the three-column layout (tree / inspector / preview) keeps sensible proportions; the preview column doesn't get squeezed illegibly small.
- [ ] Component picker modal is centered, doesn't exceed the viewport, and its own internal scroll (not the page's) activates when there are many components.

## Narrow viewport layout

- [ ] Page index remains usable down to a typical tablet width; the table doesn't overflow the viewport uncontrollably.
- [ ] Editor's three columns - check whether they remain usable or need horizontal scrolling at narrow widths, and whether that scrolling is at least not broken (no clipped, unreachable content).
- [ ] Component picker modal doesn't exceed the viewport width/height on a narrow screen.

## Empty states

- [ ] Page index with zero pages: message is clear, "New page" is still reachable.
- [ ] Editor with zero components: message is clear, "+ Add" is still reachable, preview pane doesn't error.
- [ ] Settings inspector with nothing selected: message is clear, doesn't look like a loading/broken state.
- [ ] Component picker with zero registered components: message is clear rather than a blank panel.
- [ ] Version history with zero revisions: message is clear rather than a blank list.

## Loading states

- [ ] Typing in a debounced text field doesn't visibly flicker or reset the field while the request is in flight.
- [ ] Clicking Publish gives some visible acknowledgment before/while the request completes, not just a silent pause.
- [ ] Switching selected components while a previous save is still in flight doesn't show the wrong component's data momentarily.

## Validation errors

- [ ] Page creation: submitting an empty title shows a clear error, doesn't lose the slug value already entered.
- [ ] Page creation: a duplicate slug shows a clear error identifying the conflict, doesn't silently fail.
- [ ] Image upload: a non-image file is rejected with a clear message, not a silent no-op.

## Component-picker search

- [ ] Typing a term that matches nothing shows an understandable "no results" state, not an empty white area indistinguishable from a bug.
- [ ] Search matches against both a component's label and its description.
- [ ] Clearing the search restores the full, categorized list without needing to reopen the picker.

## Category grouping

- [ ] Components with no `#[ComponentMeta]` land in a clearly-labeled "Uncategorised" group, not silently hidden.
- [ ] A category with only one component still gets its own heading, not merged into another.
- [ ] Category order is stable across repeated opens of the picker (not randomly reshuffling).

## Long component names / long field labels

- [ ] A component label long enough to wrap doesn't break the picker card's layout or overlap the description.
- [ ] A field label longer than the inspector column's width wraps rather than overflowing into the preview column.
- [ ] A long option label in a select field doesn't break the dropdown's layout.

## Large numbers of components

- [ ] A page with 30+ components: the tree list scrolls independently and remains responsive to select/reorder.
- [ ] A registry with 30+ components: the picker's category grouping and search both remain fast and legible.
- [ ] Publishing a page with many components doesn't visibly hang the UI beyond a reasonable, acknowledged wait.

## Iframe failure

- [ ] A component that throws during render: confirm the existing per-component isolation holds (the rest of the page still renders) and check what, if anything, appears in place of the failing component in the _admin_ preview versus a real published page.
- [ ] A completely broken preview view (e.g. references an undefined variable): confirm what the iframe actually shows - a Laravel error page, a blank frame, or something else - and whether that's acceptable for this release.

## Missing preview view

- [ ] With no `Inlay::previewView()` configured, the preview iframe shows the labeled "unstyled fallback" wrapper, not a blank frame or a raw exception.
- [ ] The fallback message is legible and clearly attributes the missing configuration, not just "something is wrong."

## Render failure placeholders

- [ ] Confirm current behaviour for a section referencing an unregistered handle (deleted from `config('inlay.sections')` after being added to a page) - it should be skipped, not take down the rest of the page.
- [ ] Confirm current behaviour for a section whose stored settings can no longer construct its component (e.g. a required constructor argument's type changed) - same expectation.
- [ ] Note whether either case surfaces _any_ indication to the person editing that something was silently skipped, versus only appearing in logs - this is a known gap (see README's "Current limitations"), not something to fix in this pass.

## Authentication behaviour

- [ ] With `config('inlay.middleware')` set to include `auth`, an unauthenticated visit to any admin URL is actually blocked, not just the page index.
- [ ] A logged-in, authorized user can complete the full workflow without being unexpectedly challenged again mid-session.
- [ ] Confirm `config('inlay.middleware')` is documented as insecure-by-default in the README/installation guide, and that this hasn't drifted from what's actually shipped.

## Draft/published divergence

- [ ] Editing a published page's draft does not change what a public visit currently renders.
- [ ] Publishing updates the public output immediately, without a cache or delay making it look broken.
- [ ] A page that has never been published renders nothing on a public route - confirm this is a clean empty response, not an error.

## Revision restore

- [ ] Restoring an older revision visibly updates both the draft (inspector shows the restored values) and the public output (it publishes immediately).
- [ ] The restored revision's label ("Restored from revision #N") is accurate and appears in the history list afterward.
- [ ] Restoring doesn't lose the ability to restore a _different_ older revision afterward (history isn't truncated by the act of restoring).

## Keyboard focus

- [ ] Opening the component picker moves focus into its search field.
- [ ] Closing the picker (via the close/overlay click or after adding a component) returns focus somewhere sensible, not to `<body>`.
- [ ] Every actionable icon-only button (duplicate, delete) is reachable and operable via keyboard, not just mouse hover + click.

## Tab order

- [ ] Tabbing through the page index reaches search, then each page's edit/delete actions, in visual order.
- [ ] Tabbing through the editor reaches the component tree, its actions, the inspector's fields, and the top-bar buttons in a sensible order - not jumping unpredictably between columns.
- [ ] Tabbing through the component picker stays within the modal while it's open (no focus escaping to page content behind it).

## Contrast and accessibility

- [ ] Body text, labels, and the "Uncategorised"/eyebrow small-caps text meet reasonable contrast against their background.
- [ ] Disabled/placeholder states (e.g. a disabled pagination link) are visually distinguishable from active ones without relying on color alone.
- [ ] Status badges (draft/published) are distinguishable without relying on color alone (e.g. also differ in text).
- [ ] Icon-only buttons (duplicate, delete, drag handle) have an accessible name (`title`/`aria-label`), not just a visual glyph.

## Recording findings

Log anything found here as a separate, itemized polish/defect list - do not silently fix cosmetic issues mid-checklist and do not silently expand this checklist's scope into a redesign. A finding here is "here's what's wrong and where," not "here's the new design."
