# Editor guide

This documents the editor-facing workflow in plain language - what someone managing content actually does, not the underlying code. It's written to double as the basis for future screenshots and demo material, so each section describes one distinct screen or interaction.

## Page creation

From the page index (`/inlay/pages`), click **New page**. Give it a title; a URL-safe slug is generated automatically from it as you type. You can edit the slug by hand - once you do, it stops following the title automatically, so you don't lose an intentional edit. Click **Create page** to open the new page's editor.

The page index itself lists every page, most recently updated first, with a search box that filters by title and a status badge (draft or published) next to each one.

## The editor

The editor is three columns:

1. **Components** (left) - the page's ordered list of components, with drag-to-reorder, and a **+ Add** button to open the component picker.
2. **Settings** (middle) - the currently-selected component's editable fields, generated entirely from that component's own definition. Nothing here is hardcoded per field type; adding a new field type to a component automatically produces a new inspector field with no admin changes needed.
3. **Preview** (right) - a real iframe showing the page as your own application would actually render it.

## Component selection

Click any component in the left-hand list to select it - its settings appear in the middle column and it's highlighted in the list. Selecting a different component immediately swaps the inspector to that component's own settings; nothing is shared between them.

## Adding from categories

Click **+ Add** to open the component picker: a search box at the top, and every registered component below it, grouped under the category its developer assigned (or "Uncategorised" if none was set). Type to filter by name or description instantly. Click a component's card to add it to the end of the page and select it immediately - the picker closes and its settings appear in the inspector, all showing the component's own defaults, since nothing has been edited yet.

## Settings editing

Editing a field in the inspector saves automatically - there's no separate "save settings" step. Text fields save shortly after you stop typing; toggles, selects, and checkboxes save the moment you change them. A field that only makes sense in combination with another (for example, a "button label" field that only matters when "show button" is on) appears or disappears automatically as you change the field it depends on.

Only fields the component's developer actually exposed can ever be edited or saved here - there's no way to add a setting the component doesn't declare.

## Drag-and-drop

Grab the handle (the `::` icon) on the left of any component row and drag it up or down to reorder the page. The reorder saves immediately; there's no separate confirmation step.

## Duplicate

Hover a component row to reveal its action icons, and click the duplicate icon to insert an exact copy - same component, same settings - directly after it. The copy is selected automatically so you can start editing it right away.

## Delete

Hover a component row and click the delete icon. You'll be asked to confirm, since this can't be undone from the editor - deleting removes it from the page's current draft immediately.

## Preview

The preview pane on the right reloads automatically whenever anything about the page changes - adding, editing, reordering, duplicating, or deleting a component. It always shows the page's current **draft** state, rendered through your application's own configured preview view (or a plain, clearly-labeled placeholder if none has been configured yet), so what you see is what a real visit to your site would render once published.

## Draft state

Every change you make in the editor writes to the page's draft immediately. Nothing you do here is visible to visitors of your live site until you explicitly publish - you can experiment, undo by hand, and leave a page half-edited without any risk to what's currently live.

## Publishing

Click **Publish** in the top bar. This takes a complete snapshot of the page's current draft - every component, in order, with every setting fully resolved - and makes it the page's new live, public version. A confirmation briefly appears next to the button. Publishing again later creates a new snapshot; it never overwrites or loses a previous one.

## Revision restore

Click **History** in the top bar to see every past publish, most recent first. Click **Restore** next to an older one to bring the page's live content back to exactly that state - this both updates the draft you're editing and publishes it immediately, creating a new revision labeled with which one it was restored from. Restoring is not a preview-only action: it takes effect on your live site right away, the same as clicking Publish.
