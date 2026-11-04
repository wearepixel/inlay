# Landing pages: starter blocks and an MCP server

Status: approved direction (2026-11-04). Builds on Beta 0.1.

## Goals

1. A host can install a working landing-page block set in one command and restyle it.
2. A host's AI agents can compose, preview and (optionally) publish Inlay pages over MCP,
   either through Inlay's own MCP server or mixed into an MCP server the host already runs.
3. The agent gets enough feedback to iterate a page towards a reference design (e.g. a
   Figma frame) without a human in the loop for every step.

## Non-goals

- Agents authoring new blocks. A Figma frame with no matching block is reported as a gap;
  a developer builds the block.
- Remote-URL image import (SSRF surface). Images arrive as base64.
- Laravel 11 support for the MCP feature. laravel/mcp ^1.0 needs Laravel 12+; the rest of
  Inlay keeps supporting 11.

## 1. Starter kit

`php artisan inlay:install --starter=landing` copies single-file blocks from
`stubs/starters/landing/blocks` into `resources/views/inlay/blocks` and a `LandingPage`
Page Definition into `app/Inlay/LandingPage.php`. Existing files are never overwritten
without `--force`. The command prints the two registration lines to add
(`Inlay::singleFileBlocks(...)`, `Inlay::pageDefinition(...)`).

Blocks: hero, logo-cloud, feature-grid, split-media, stats, testimonials, pricing, faq,
cta-band, rich-text. Tailwind utility classes, no host-specific tokens, every field with a
sensible non-placeholder default, `description` written for an agent to match against.

## 2. Domain layer (no MCP dependency)

Extracted so the admin and MCP share one set of rules:

- `Support\SettingsCoercer` - the coercion currently private to `PageEditor`. The editor
  delegates to it. Behaviour unchanged.
- `Composition\SettingsValidator` - strict validation of agent-supplied settings. Unlike
  the coercer (which silently drops/repairs), it reports every problem with a dotted path,
  a message and a hint: unknown key, wrong type, unknown select option, required field
  empty, max length exceeded, disallowed URL scheme, unknown image path.
- `Actions\CreatePage` - extracted from `PageCreate` (create, seed default blocks,
  homepage).
- `Actions\ReplacePageComposition` - atomically replaces a page's sections. Rejects (all or
  nothing) when: a block is unregistered or not allowed by the page's definition; the
  definition is unresolved; a locked block's existing position changes or it is removed;
  a required block is absent; any settings error. Stored settings stay sparse - only keys
  the caller supplied are stored.
- `Actions\UpdatePageSection` - merges a settings patch into one section, same validation.
- `Composition\CompositionReport` - renders a composition section by section and reports:
  per-section render errors (a block that throws does not hide the others), fields still at
  the block default, required fields empty, Page Definition violations.

## 3. Preview links

`Inlay::previewLinkRoutes()` (opt-in, like every other Inlay route) registers
`GET /inlay-preview/{token}` behind the `signed` middleware. A token names either a stored
page draft or a proposed composition held in cache. Links expire after
`config('inlay.preview_links.ttl')` minutes (default 30). The route renders through the
host's preview view, so it looks exactly like the admin preview. It sends
`X-Robots-Tag: noindex` and never renders a page the token does not name.

## 4. Screenshots

`Contracts\Screenshotter::capture(string $url, int $width): string` (PNG bytes). Drivers:
`null` (default - no screenshots) and `browsershot` (spatie/browsershot, suggested). The
screenshotter loads the signed preview link, so it must be able to reach the app's URL.

## 5. MCP

Only active when laravel/mcp is installed (`class_exists` guard, like Livewire).

Tools (all names prefixed `inlay-`):

| Tool | Writes | Ability |
|---|---|---|
| inlay-list-blocks | no | manage |
| inlay-list-page-definitions | no | manage |
| inlay-list-pages | no | manage |
| inlay-get-page | no | manage |
| inlay-create-page | draft | manage |
| inlay-replace-sections | draft | manage |
| inlay-update-section | draft | manage |
| inlay-upload-image | disk | manage |
| inlay-preview-page | no (cache) | manage |
| inlay-publish-page | public | publish + `inlay.mcp.allow_publish` |

Every tool checks a Gate ability for the MCP request's user: `inlay.manage-pages` and
`inlay.publish-pages` by default (configurable). An undefined ability denies. Publishing is
also off unless `config('inlay.mcp.allow_publish')` is true.

Two ways to mount:

```php
// A: Inlay's own server
Mcp::web('/mcp/pages', \WeArePixel\Inlay\Mcp\InlayServer::class)->middleware([...]);

// B: inside an existing server
protected array $tools = [MyTool::class, ...\WeArePixel\Inlay\Mcp\InlayTools::all()];
```

Because mode B has no Inlay-owned server instructions, each tool's description carries
its own workflow guidance.

`inlay-preview-page` accepts an optional proposed `sections` array (dry run, nothing
saved). It returns the composition report, a signed preview link, desktop and mobile
screenshots when a driver is configured, and is linked to an MCP App resource
(`ui://inlay/preview`) that shows the live preview inline in clients that support MCP Apps.

## Risk tiers

Tier 1 (test first): Gate checks and the publish switch, signed preview route, image upload
validation, `ReplacePageComposition` guardrails. Tier 2: coercer/CreatePage extraction,
report, starter install. Tier 3: block stub markup.
