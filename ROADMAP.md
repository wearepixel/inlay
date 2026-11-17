# Roadmap

Inlay lets developers build sections and blocks in their own Blade and CSS, and lets the people who run the site - project managers, business owners, marketing teams - edit every page, with drafts and version history. Pre-1.0: the API can still change.

## Now

- **Sections and blocks.** Pages are built from sections; sections can hold blocks editors arrange freely. Click anything in the preview to edit it.
- **Your own admin.** Render Inlay inside your admin layout (`inlay.admin.layout`) or mount its components on your own routes, guarded by `inlay.admin.ability`.
- **Site settings.** Contact details, header and footer content, edited in the admin.

## Next

- **One-command setup.** `inlay:install` detects the CSS framework, Livewire, auth and an existing admin layout, then writes the service provider registration, routes, admin guard and preview view for you.
- **Showcase starter kits.** Production-quality layouts to start from - bento grids, split heroes with layered media, pricing tables, testimonial walls, logo marquees - in Tailwind v4 and Bootstrap 5, plus the framework-free plain variant.
- **Existing-project guide and `inlay:doctor`.** A step-by-step guide for adding Inlay to an established app, and a command that checks the setup and says exactly what is missing.
- **Rich text editor.** A proper formatting toolbar for rich text fields, in place of the current HTML textarea.
- **Import a Blade page.** Turn an existing Blade page into editable sections - see `docs/superpowers/specs/2026-11-18-blade-page-import-design.md`.

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
