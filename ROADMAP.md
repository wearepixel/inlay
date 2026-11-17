# Roadmap

Inlay lets developers build sections and blocks in their own Blade and CSS, and lets the people who run the site - project managers, business owners, marketing teams - edit every page, with drafts and version history. Pre-1.0: the API can still change.

## Now

- **Sections and blocks.** Pages are built from sections; sections can hold blocks editors arrange freely. Click anything in the preview to edit it.
- **Your own admin.** Render Inlay inside your admin layout (`inlay.admin.layout`) or mount its components on your own routes, guarded by `inlay.admin.ability`.
- **Site settings.** Contact details, header and footer content, edited in the admin.
- **One-command setup.** `inlay:install` detects the stack and writes the wiring; `inlay:doctor` checks it.
- **Starter kits.** A landing kit in Tailwind v4, Bootstrap 5 and plain CSS, and a showcase kit of complex Tailwind layouts.
- **Import a Blade page.** `inlay:import` turns an existing page into editable sections and a draft page.

## Next

- **Rich text editor.** A proper formatting toolbar for rich text fields, in place of the current HTML textarea.
- **Smarter import.** Recognise repeated markup (cards, list items) as a repeater or a blocks area, and honour `data-inlay-*` hints in the source.
- **Bootstrap showcase kit.** The showcase layouts in Bootstrap 5.

## Later

- **Reusable sections.** A section edited once and shown on many pages (a call to action, a footer band).
- **Scheduling.** Publish and unpublish at a set time.
- **Roles per page.** Who may edit or publish which pages, beyond the single admin ability.
- **Localisation.** Translated page content.

## Community

Good first contributions, where the core team would welcome a maintained package:

- **Filament plugin** (`inlay/filament`). Register Pages and Site settings as Filament pages inside an existing panel, using Inlay's embeddable components, `<x-inlay::frame>` and `Inlay::adminUrlsUsing()`. Theme the editor from the panel's colours.
- **Nova and Backpack adapters** on the same pattern.
- **Starter kits** for other CSS frameworks (Bulma, UIkit) and design systems.
