# Importing a Blade page into an editable page

Status: proposal, not built.

## Goal

A developer builds a page the way they already do - one Blade file of Tailwind sections - and Inlay turns it into an editable page with versions, without hand-writing each section's settings first.

## Proposed flow

1. `php artisan inlay:import resources/views/pages/about.blade.php --path=about`
2. The command splits the file into its top-level `<section>` elements (or elements marked `data-inlay-section="name"`).
3. For each one it writes a single-file section to `resources/views/inlay/sections/{name}.blade.php`, lifting editable content into settings:
   - text in headings and paragraphs becomes `text` / `textarea` / `richtext` settings, with the current text as the default
   - `<img src>` becomes an `image` setting, `alt` a `text` setting
   - `<a href>` with text becomes a `link` setting
   - repeated sibling markup (cards, list items) becomes a `repeater`
4. It creates a draft page whose composition is those sections in order, so the editor shows the page exactly as built.
5. The developer reviews the generated sections - names, labels, which text should stay fixed - before publishing.

## Open questions

- How much to lift automatically vs. requiring `data-inlay-*` hints in the source. Hints are predictable; heuristics are magic.
- Whether repeated markup should become a repeater or a blocks area.
- Whether the MCP agent path (an agent reading the Blade file and calling `inlay-save-section`) covers this well enough to skip a command.
