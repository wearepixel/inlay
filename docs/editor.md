# Editor guide

This documents the editor-facing workflow in plain language - what someone managing content actually does, not the underlying code. It's written to double as the basis for future screenshots and demo material, so each section describes one distinct screen or interaction.

## Page creation

From the page index (`/inlay/pages`), click **New page**. Give it a title; a URL-safe slug is generated automatically from it as you type. You can edit the slug by hand - once you do, it stops following the title automatically, so you don't lose an intentional edit. Click **Create page** to open the new page's editor.

The page index itself lists every page, most recently updated first, with a search box that filters by title and a status badge (draft or published) next to each one.

## The editor

The editor is two panes, not three:

1. **The contextual sidebar** (left) - shows the page's block tree by default, and switches to a selected block's settings when you're editing one. The two never share space; selecting a block replaces the tree rather than appearing alongside it.
2. **Preview** (right) - a real iframe showing the page as your own application would actually render it, given the dominant share of the editor's width. This is a visual composition tool built around the preview, not a settings form with a preview bolted on the side.

## Block selection

Click any block in the tree to select it - the sidebar swaps from the tree to that block's settings, showing its display name and a guessed instance label (e.g. `Hero - "Welcome"`) so you can tell apart several blocks of the same type. Click **&larr; Blocks** at the top of the settings pane, or press **Escape**, to go back to the tree - the block you were editing stays highlighted there, so you always know where you left off.

## Adding from categories

Click **+ Add** to open the block picker - a drawer sliding in from the same edge as the sidebar, with a search box already focused and every registered block below it, grouped under the category its developer assigned (or "Uncategorised" if none was set). Type to filter by name or description instantly. Click a block's card to add it to the end of the page and select it immediately - the drawer closes and its settings appear in the sidebar, all showing the block's own defaults, since nothing has been edited yet.

## Settings editing

Editing a field in the inspector saves automatically - there's no separate "save settings" step. Text fields save shortly after you stop typing; toggles, selects, and checkboxes save the moment you change them. A field that only makes sense in combination with another (for example, a "button label" field that only matters when "show button" is on) appears or disappears automatically as you change the field it depends on.

Only fields the block's developer actually exposed can ever be edited or saved here - there's no way to add a setting the block doesn't declare.

## Drag-and-drop

Grab the handle (the `::` icon) on the left of any block row and drag it up or down to reorder the page. The reorder saves immediately; there's no separate confirmation step. Reordering is only available from the tree, so if you're currently editing a block's settings, go back first. If you'd rather not drag: focus a row (Tab, or the arrow keys below), then use the keyboard shortcuts below - reordering with the keyboard never opens the row's settings, so you can stay in the tree the whole time.

## Duplicate

Hover a block row to reveal its action icons, and click the duplicate icon to insert an exact copy - same block, same settings - directly after it. The copy is selected automatically, and its settings replace the tree in the sidebar, so you can start editing it right away.

## Delete

Hover a block row and click the delete icon (or press the **Delete** key while it's focused). You'll be asked to confirm, since this can't be undone from the editor - deleting removes it from the page's current draft immediately. Deleting the block you're currently editing (or another browser tab/session deleting it out from under you) safely returns the sidebar to the tree rather than leaving a settings pane open for a block that no longer exists.

## Instance labels

The tree identifies each block by its registered display name, plus a short instance detail guessed from its own settings - `Hero - "Welcome"` rather than a bare, indistinguishable `hero` repeated for every instance. The guess checks (in order) a setting named `heading`, `title`, `name`, or `label`, then falls back to the first short text setting declared - never a textarea, rich text, image, or structured field, so a list label can never surface long-form or sensitive content. A block can name a specific field explicitly instead of relying on the guess - see [docs/blocks.md](blocks.md#instance-labels).

## Organized settings

A block with a lot of settings can group them under named, collapsible headings, and - once there's more than one - into tabs across the top of the inspector (Content, Appearance, SEO, and so on). Both are entirely up to the developer who built the block; a simple block with a handful of settings shows them flat, with no extra structure to click through. A field can also carry a small badge (e.g. "New") next to its label, and a required field that's still empty shows a calm reminder rather than an alarming error - you're still composing the page, not submitting a form.

## Conditional settings

Some settings only make sense in combination with another - a "background colour" field that only matters when "background type" is set to "Colour", say. These appear and disappear instantly as you change the field they depend on, with a brief animation rather than a jump, and without reloading anything.

## Preview

The preview pane on the right reloads automatically whenever anything about the page changes - adding, editing, reordering, duplicating, or deleting a block. It always shows the page's current **draft** state, rendered through your application's own configured preview view (or a plain, clearly-labeled placeholder if none has been configured yet), so what you see is what a real visit to your site would render once published. It takes up roughly 70-75% of the editor's width regardless of how many settings the selected block has - the sidebar's width is fixed, not squeezed narrower by a long settings form.

Use **Desktop / Tablet / Mobile** above the preview to check how the page looks at different widths - this resizes the preview frame itself, not a simulated device, and your choice is remembered the next time you open the editor. The refresh icon reloads the preview immediately if you ever want to force a fresh render.

On a narrow browser window or a small screen, the editor switches to one panel at a time - an **Edit / Preview** control above the sidebar swaps between the tree-or-settings pane and the preview, rather than trying to fit both into unusably narrow columns.

## Draft state

Every change you make in the editor writes to the page's draft immediately. Nothing you do here is visible to visitors of your live site until you explicitly publish - you can experiment, undo by hand, and leave a page half-edited without any risk to what's currently live. While a change is being saved, a brief "Saving…" indicator appears next to the page's status; the editor never leaves you guessing about whether an edit landed.

## Publishing

Click **Publish** in the top bar. This takes a complete snapshot of the page's current draft - every block, in order, with every setting fully resolved - and makes it the page's new live, public version. The button shows "Publishing…" while the request is in flight, then a confirmation briefly appears. Publishing again later creates a new snapshot; it never overwrites or loses a previous one.

## Keyboard shortcuts

- **Block tree**: Up/Down moves focus between blocks; Enter opens the focused one's settings; Alt+Up/Alt+Down reorders it (the same reorder a drag would perform) without opening it; Delete removes it (with the same confirmation as clicking the delete icon).
- **Settings**: Escape returns to the block tree, keeping the same block highlighted and focused there.
- **Block picker**: opens with the search box already focused; Up/Down highlights a result; Enter adds the highlighted one; Escape closes the picker before it would otherwise close the settings pane behind it.

## Revision restore

Click **History** in the top bar - the sidebar switches to a list of every past publish, most recent first, in place of whatever it was showing. Click **Restore** next to an older one to bring the page's live content back to exactly that state - this both updates the draft you're editing and publishes it immediately, creating a new revision labeled with which one it was restored from. Restoring is not a preview-only action: it takes effect on your live site right away, the same as clicking Publish. The sidebar returns to the block tree afterwards, since a restore replaces every block on the page.
