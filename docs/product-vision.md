# Product Vision

Date: 2026-08-02
Status: Founding manifesto — read before any future architecture, table, route, or service is discussed.

This document does not describe what the software does. It describes why it should exist at all, and what it must never become. Every future technical decision should be checked against this document, not the other way around.

---

## Why does this project exist?

Every Laravel agency and every serious solo developer eventually hits the same moment: a client, a founder, or a marketer says "can I edit this myself?" — and there is no good answer.

The honest options today are all compromises:

- **WordPress** gives you a real visual editor, at the cost of your entire stack. You stop writing Blade and start writing PHP inside someone else's plugin architecture, inheriting a second ecosystem's security surface, update cadence, and hosting requirements, just to let someone change a hero heading.
- **Statamic** is the closest relative in spirit — Laravel-native, code-first, genuinely well built — but it wants to _be_ your application. Adopting it usually means modeling your content inside Statamic's collections and blueprints, not bolting visual editing onto domain models and components you've already written.
- **Filament** and **Nova** solve a different problem beautifully: CRUD over your Eloquent models for your internal team. Neither has a concept of a visual canvas, because neither is trying to. Handing a marketer a table of fields is not the same as letting them arrange a page.
- **Craft CMS** shares WordPress's category problem with better craftsmanship — it's still not Laravel, and it still asks you to model your content inside its system rather than yours.
- **Builder.io** solves the visual part well, but it is a hosted, framework-agnostic SaaS. The integration model centers Builder's cloud, not your Laravel application, and the content it produces is not something you own the way you own a database table.
- **Gutenberg** is WordPress's own answer to "make it more like a page builder," and developers have spent a decade fighting the markup it produces and the JavaScript-heavy editing model it imposes on a PHP application.
- **Rolling your own** is what almost everyone actually does, and it is the real competitor this project has to beat. Every agency converges on the same shape eventually — a slug-to-component registry and a JSON blob of props — because it's the obviously correct architecture. But it's built once, under deadline, for one client, and it never gets the polish, the drag-and-drop, the revision history, or the security hardening that a real product deserves, because it was never treated as a product. It's built to survive one project, not to be trusted with the next fifty.

Nobody in the Laravel ecosystem is solving this properly. Not "nobody has tried" — plenty of teams have built their own version of exactly this, this repository's own history included. Nobody has made it _good enough to stop rebuilding_. That is the gap. This project exists to be the version an agency builds once, at the quality of a real product, and never has to build again — for their own team, and eventually, for the entire Laravel ecosystem.

---

## Who is this for?

**The ideal customer is a Laravel agency or a solo Laravel developer who builds marketing sites, content-driven public pages, or a SaaS product's own marketing surface, and who has already written (or is about to write) real Blade or Livewire components for that content.** They are not looking for a CMS. They are looking to stop being the person who edits the homepage by hand every time someone asks.

- **Solo developers** — yes, strongly. A solo developer doing client work is the person most punished by "can you just change this text" requests arriving forever after launch. This gives them an exit.
- **Agencies** — yes, this is the primary audience, and it always has been. This is the feature every agency rebuilds per client, worse each time under worse deadlines. One good version, reused across every engagement, is a direct multiplier on margin and on reputation.
- **SaaS founders** — yes, specifically for the marketing shell around the product: landing pages, pricing, campaign pages. Not for the application itself.
- **Enterprise teams** — partially. An enterprise team inside a Laravel shop that wants to let its marketing department manage pages, yes. An enterprise team that wants multi-approval governance, translation management, and a headless content API as a platform — not yet, and possibly never; that is a different, heavier product.
- **Internal applications** — no. Internal tools need forms and tables over business data, not a visual canvas for composing marketing pages. That is Filament's and Nova's job, and they already do it well.
- **Marketing websites** — yes, unambiguously the core use case. This is a marketing-page tool that happens to be able to live inside any Laravel application, not an application framework that happens to also build pages.

**Who should not install this:**

- Anyone who wants a headless content API with no admin UI of its own — this is not that, and pretending otherwise would betray the whole point.
- Anyone who wants a no-code tool that lets a non-developer _invent_ new kinds of content from nothing — this always requires a developer to define what's possible first. If the goal is zero developer involvement, ever, this is the wrong tool.
- Anyone building a data-heavy internal dashboard — reach for Filament or Nova instead.
- Anyone who needs enterprise content governance (multi-locale workflows, approval chains, compliance audit trails) on day one.

---

## What should this product become?

Five years from now, ignoring every current technical limitation: this should be the package a Laravel developer reaches for as reflexively as they reach for Livewire when they need interactivity, or Filament when they need an admin panel. Not "the page builder people settle for" — the page builder people are a little proud to have chosen.

If it succeeds, here is what people would say about it:

- "We stopped building this ourselves the day we found it."
- "It's the only page builder I've used that a developer actually recommended to another developer."
- "It doesn't feel like WordPress wearing a Laravel costume. It feels like it was always supposed to exist."
- "Our clients love using it, and our developers don't resent building for it."
- "It's the kind of package where you read the source once and just trust it after that."

The reputation to earn is craftsmanship and restraint — the same reputation Livewire earned for making server-driven interactivity feel inevitable, and Filament earned for making admin panels feel like a solved problem. Not the biggest page builder. The one nobody feels the need to replace.

---

## What does it refuse to become?

This list matters more than any feature list. Every future decision gets checked against it.

- **It will never allow visual HTML editing.** An editor arranges and fills in components a developer wrote. They never touch markup.
- **It will never allow arbitrary CSS or class editing.** Presentation is the developer's decision, made once, in code, not remade per-page by whoever is editing content that day.
- **It will never store generated HTML as canonical content.** The database holds structured, typed, queryable data — component identity and props — never a rendered blob that only means something once interpreted back through a specific version of a specific template.
- **It will never lock a developer in.** No proprietary content format, no format only this package can read, no dependency that can't be cleanly removed, no data that isn't sitting in the host application's own database in a shape a developer could read with `SELECT *` if they had to.
- **It will never require a proprietary component format.** A component is a real Blade or Livewire class the developer already knows how to write, test, and reason about — not a DSL, not a schema file in a format invented for this package.
- **It will never rely on magic.** No behavior a developer can't trace by reading the code. No reflection-based wiring they can't step through, no convention so clever it becomes a mystery six months later.
- **It will never try to replace code.** It composes what a developer built. It does not generate functionality, business logic, or design decisions on anyone's behalf.
- **It will never force a specific frontend stack, build tool, or design system on the host application.** The moment it starts dictating how a consuming app builds its frontend, it has stopped being a page builder and started being a framework, which is not what it is for.
- **It will never become a general-purpose content-type modeling platform.** It solves _page composition_. It is not trying to become Craft or Contentful, with forty field types and an ambition to model every kind of content an application might ever have.
- **It will never build a plugin or theme marketplace as a business model.** No paid-plugin economy, no walled garden. The value is the craft of the thing itself, not a storefront bolted onto it.
- **It will never become the place business logic lives.** It records what is on a page. It does not decide how the business works.

---

## Core philosophy

These are not borrowed from another product's values page. They are what falls out of actually trying to solve this problem honestly.

1. **The component is the unit of truth.** Not the page, not a template, not a block library — a real class a developer wrote, tested, and can `git blame`.
2. **Developers own the code. Editors own the content.** The line between those two never blurs, in either direction.
3. **An editor can never do more than a developer explicitly allowed.** Every editable field is a deliberate decision, not a default.
4. **Content stays structured, forever.** Never a blob of markup, never a format that only means something inside this package's own renderer.
5. **Nothing is invented on anyone's behalf.** The package renders what was built. It does not generate markup, styling, or behavior.
6. **A constraint is a feature.** A form that limits what's possible produces better content, more reliably, than a canvas that permits everything.
7. **Your data is yours.** It lives in your migrations, in your database, queryable with the tools you already know.
8. **Boring is the goal, not the insult.** Publishing, drafts, and revisions should work exactly as a Laravel developer already expects, with zero surprises.
9. **It should feel like it could have shipped with Laravel.** Same restraint, same conventions, same instinct for convention over configuration.
10. **An install should look finished, not scaffolded.** The admin is complete on day one — never a bare component the developer is expected to style themselves.
11. **New power comes from writing a new component, never from a hidden setting.** Capability grows in code, where it can be reviewed, tested, and understood.
12. **A developer is never trapped.** No format, dependency, or convention that can't be cleanly walked away from.
13. **Every feature request is measured against one question: does this belong in the developer's own component instead?** Most of the time, the honest answer is yes.
14. **Trust compounds one boring release at a time.** This runs on infrastructure a client's business depends on. It should never feel experimental.
15. **A developer should never have to babysit an editor, and an editor should never need to wait on a developer for routine work.** Both of those failures are the same failure, seen from different sides.
16. **Restraint is where the delight lives.** The calmest interface wins over the most powerful-looking one, every time.
17. **A revision history is a promise, not a feature.** Nothing an editor does should ever be truly, quietly destructive.
18. **If it can't be explained in one honest sentence, it isn't ready.**
19. **It earns its place by disappearing into the host application — never the other way around.**

---

## The emotional experience

If Statamic feels calm, Laravel feels elegant, Linear feels fast, Ray feels joyful, and Forge feels trustworthy — this should feel **composed**. Both meanings at once: the emotional state of someone who is unhurried and sure of themselves, and the literal act of arranging something with care. That double meaning is not a coincidence; it is the whole point.

For a developer, the feeling is **relief** — the quiet exhale of watching a category of chore disappear. Not excitement, not novelty. Relief, the way a good ORM or a well-designed queue system feels: "oh, thank god, I don't have to think about this anymore."

For an editor, the feeling is **quiet competence** — the confidence of someone handed a small, well-organized toolbox where every tool is exactly where they expect it, rather than a warehouse of possibilities they're afraid to touch. They should never feel like they're operating software. They should feel like they're arranging a page.

What that means, concretely, in every surface of the product:

- **Visual design** — unhurried and generous. Real whitespace, not dashboard density. A restrained neutral palette with a single confident accent used only where it means something (draft vs. published, an error, a warning) — never decoration for its own sake.
- **Interaction design** — every action confirms itself quietly. Saving is a small, unobtrusive state, never a modal demanding acknowledgment. Nothing requires a second guess. Drag-and-drop has real weight and easing behind it — it should feel physically satisfying to drop a section into place, not mechanical.
- **Typography** — a highly legible, workmanlike interface face, and monospace only where it's telling the truth about something technical (a component handle, a field name) — never a decorative face pretending this is a "creative tool."
- **Spacing** — closer to a well-set document than a data-dense dashboard. Content is allowed to breathe because the product's job is to help someone think about content, not to cram in controls.
- **Animation** — minimal, fast, and purposeful. Motion exists to clarify a state change — a section sliding into its new position after a drop — never as decoration, and never so much of it that it starts to feel like it's performing for the user instead of helping them.
- **Colour** — mostly neutral. Colour is reserved for meaning, not mood — status, warnings, the one accent that says "this is the important action on this screen."
- **Language** — plain and first-person-friendly. "Publish," not "Deploy content object." Errors explain what to do next, never apologize, never hide behind jargon.
- **Discoverability** — nothing important is three clicks deep in a settings modal. Adding a section should feel like opening a small, curated shelf, not scrolling an infinite catalogue.
- **Onboarding** — the first five minutes should feel like the tool already understands the application it was installed into. A sensible default page, a real preview, never an empty screen staring back at a confused new user.

Every future UI decision should be checked against one question: does this still feel composed?

---

## What is the fundamental building block?

The architecture work that preceded this document proposed four concepts: Sections, Modules, Layouts, and Templates. Held up against the value placed on conceptual simplicity here, three of those four don't survive scrutiny as _separate_ concepts, and the fourth survives only in a much smaller form.

**Sections and Modules are the same thing wearing a label.** A "module" was already, technically, nothing more than a section whose own code happens to reach into the host application's data. That difference lives entirely inside the component's own implementation — it requires no separate interface, no separate registration verb, and no separate rendering path. Calling it out as a distinct _concept_ borrows CMS taxonomy (Craft's "modules," WordPress's dynamic blocks) without a real reason rooted in this system. It survives only as an optional label in a picker's grouping — a hint for a human browsing a list, never a distinction the code needs to know about.

**A layout is the same primitive, placed in a different slot.** A layout renders a header, navigation, footer, and a place for page content to appear — but "a component that accepts a slot for what goes inside it" is not architecturally different from any other component. It doesn't need its own interface. It needs one piece of metadata: where it's allowed to be used. A component marked as usable as a layout is placed once, around everything else, instead of being one of several things composed within the page.

**A template is the genuine outlier, and it survives in a much smaller form.** A template isn't rendered at all — it's a constraint ("these components are allowed," "start new pages with this arrangement"). Forcing it to be a fourth registered, renderable concept would be dishonest to what it actually is. It doesn't need to exist as a code contract from day one. If it earns its place at all, it earns it as _data_ — a saved starting arrangement of real components, something closer to "duplicate this page as a starting point" than to a class a developer implements. Most teams don't need governance over what's allowed together on day one. They need a sensible default, which is a much smaller problem.

**The answer: everything is a Component.** One interface. One registration path. One rendering pipeline. What used to be four concepts collapses into one thing — a component a developer wrote — described by metadata about where it's allowed to appear, not by which of four boxes it was forced into. Conceptual simplicity was worth more here than matching a decade of CMS terminology, and the system is better for giving it up.

---

## The developer experience

**Day 1.** Install it, register a couple of Blade components that already exist in the application, create the first page, and feel something close to surprise: it already looks finished. Not a scaffold waiting to be styled — a real, considered admin screen, on day one, with nothing to configure before it looks like a product.

**Week 1.** The bespoke content sections that used to live in raw Blade views have been rewritten as real components. A client or a marketer is editing the homepage without supervision. The Slack message that used to say "can you change the hero text" has stopped arriving.

**Month 6.** A dozen components exist, shared as a convention across several projects at the same agency. Starting a new client engagement's "let them edit their own homepage" requirement takes an afternoon, not a sprint, because it was solved once and reused, not rebuilt.

**Year 3.** It has become invisible in the way genuinely good infrastructure becomes invisible — nobody remembers deciding to keep using it, the way nobody remembers deciding to keep using Eloquent. New hires are handed the docs and are productive the same day, because the whole thing still fits in someone's head.

The way it avoids becoming cumbersome over that time is a discipline, not an accident: every new capability still has to arrive as a component a developer writes. The package's own surface area is not allowed to grow into a thousand configuration flags. It is allowed to grow only by getting quieter — fewer decisions required over time, not more.

---

## The editor experience

A non-technical editor should experience this as arranging tiles on a board, not as operating software. They should never be confused about what they're allowed to touch — if a field is on the screen, it's safe to change; there is no dead-end setting, no field that silently does nothing, no way to see a raw code editor by accident. They should never be confused about what happens when they save — the change is immediate, visible, and safe, because nothing they do is ever truly destructive; there is always a way back.

They should be confused by exactly nothing that matters to their job, and given exactly zero access to anything that isn't theirs to touch: no CSS, no HTML, no component internals, no sense that there's a more powerful mode hiding somewhere they haven't found yet. The ceiling on their power is drawn once, by a developer, per component — full control over arrangement and over every field a developer chose to expose, and no control whatsoever over anything structural. That ceiling is a kindness, not a restriction: it's what makes it impossible for them to break something they didn't know they could break.

---

## The ecosystem

This is not Horizon, which is a window onto a subsystem — queues — that already belongs entirely to Laravel core. It is not Pulse, a passive observability layer. It is not Forge, which is infrastructure and hosting, an entirely different category and a different kind of business.

The closest philosophical relative is Filament — not because they compete, but because they solve mirrored problems with the same instinct for craft. Filament is the missing admin panel for a Laravel application's own Eloquent models. This is the missing content-editing surface for a Laravel application's own marketing and content pages. A single project might reasonably run both at once: Filament for the internal back office, this for the public-facing pages a non-developer needs to touch. Kinship, not overlap.

It should feel like it belongs on the same shelf as Livewire, Filament, and the well-known Spatie packages — the tier of third-party Laravel software developers trust enough to stop asking "is this any good?" before installing it. It should never claim to be Laravel itself, and it should never try to. Its relationship to the rest of the ecosystem is complementary by design: built on Livewire rather than around it, happy to sit beside Filament rather than compete with it, and never reaching for territory — routing, auth, queues — that Laravel and its own first-party tools already own.

---

## Naming

"Page Builder" describes a category. It doesn't describe this project, and it never should have. Twenty candidates, each aiming for the same register as the rest of the Laravel ecosystem's naming — Forge, Horizon, Pulse, Nova, Vapor, Sail, Herd, Valet, Cashier, Scout, Sanctum, Fortify, Breeze, Envoyer, Dusk, Reverb, Pennant, Pint, Volt: short, evocative, often literal objects or crafts, never a compound "ContentFlow"-style startup name, never ending in "CMS."

1. **Anvil** — the tool something solid gets shaped on.
2. **Loom** — weaving separate, structured pieces into one thing.
3. **Lattice** — a structure that supports and shapes growth without dictating its exact form; the closest metaphor to schema-driven, developer-defined flexibility.
4. **Cairn** — a deliberately placed, composed structure, built one piece at a time.
5. **Plinth** — the quiet foundation something else is displayed on.
6. **Vellum** — fine material meant to be written on and kept.
7. **Draft** — a genuine double meaning with the product's own draft-then-publish model; unusually ownable.
8. **Ledger** — a record of exactly what's true, though it risks reading as financial software at a glance.
9. **Foyer** — the considered space a visitor experiences first.
10. **Atlas** — a confident, timeless word for a collected set of composed pages.
11. **Threshold** — the boundary between structure and content, crossed deliberately.
12. **Bower** — a light garden structure; understated rather than literal.
13. **Nave** — the open, structured space at the center of a building; distinctive and quiet.
14. **Arbor** — a structure built to support something growing.
15. **Kiln** — where raw material is fired into something finished and durable.
16. **Truss** — blunt, honest, structural — no pretense about it.
17. **Parlour** — a composed room, prepared in advance for someone to be received in.
18. **Marquee** — the headline structure, a little bolder than the rest of the list.
19. **Cornerstone** — the first stone a structure is built from; more literal, safer, less distinctive than the rest of this list.
20. **Hearth** — the calm, central point a room is arranged around.

If three had to be shortlisted: **Lattice**, for how precisely it captures a structure that shapes without constraining; **Loom**, for how naturally it carries the idea of composing separate pieces into one considered whole; and **Draft**, for being genuinely, cleverly ownable by a product whose entire model already revolves around drafting and publishing.

---

## Mission statement

**One sentence.** We give Laravel developers a way to let anyone compose a page from the components they already built, without giving up their code, their data, or their craft.

**One paragraph.** This exists because the Laravel ecosystem has never had a proper answer to "can I edit this myself?" — only compromises borrowed from other ecosystems, or bespoke tools built once, under deadline, and never trusted again. It gives a developer a way to write real Blade components, decide exactly what's safe for someone else to touch, and hand over a genuinely well-built admin experience for arranging and publishing pages built from those components — with drafts, previews, and revision history that behave the way a Laravel developer already expects everything else in their stack to behave. It never generates markup, never invents functionality, and never asks a developer to give up ownership of their own code or data to get there.

**One page.** For as long as Laravel developers have built for clients, marketers, and founders, there has been a moment at the end of every project where someone who isn't a developer needs to be able to change something on a page, and the developer has had to choose between compromises: hand the entire stack over to WordPress, adopt a full CMS platform that wants to own the application, build something bespoke and hope it survives contact with a real editor, or simply agree to be the person who edits the homepage forever. None of those are a solution. They're the least bad option available, chosen under deadline, and quietly resented afterward. This exists to end that choice. It starts from a simple, non-negotiable premise: a developer writes real code, decides deliberately what part of that code is safe to hand over, and everything else — the page composition, the drafting, the previewing, the publishing, the history of every change ever made — is handled by a piece of software good enough that nobody involved ever has to think about building it themselves again. Content stays structured and owned by the application it lives in, never trapped inside a proprietary format or a blob of generated markup. The developer never loses control of their code. The editor never gains more power than was deliberately given to them. Every decision this project makes, from its architecture to the words used in its interface, is weighed against a single test: does this feel like it could have shipped with Laravel itself, built with the same restraint, the same craft, and the same refusal to make the developer holding it feel like they compromised. If it succeeds, it will not be remembered as the biggest page builder available to Laravel developers. It will be remembered as the one that made every other option feel unnecessary.

---

## Elevator pitch

_(Taylor Otwell: "So... what does it do?")_

"You write your Blade components exactly like you already do. We give you an admin screen where someone who isn't a developer can drag those components onto a page, fill in only the fields you decided were safe to hand over, preview it exactly as it'll appear live, and publish it — with a full version history, so nothing they do is ever truly destructive. Nothing gets generated, nothing gets stored as raw HTML, and you never lose ownership of your own code. It's the 'let the client edit their own homepage' feature every agency ends up building badly, under deadline, at least once a year. We built it once, properly, so nobody has to build it again."

---

## If this project succeeds

Ten years from now, success looks like this, and none of it is measured in revenue or stars.

A developer joining a Laravel agency for the first time is handed the same set of expectations as they'd be handed for testing, for queues, for authentication — and this is one of them, without anyone needing to justify why. It isn't a tool the agency chose after evaluating alternatives. It's simply part of how a Laravel project gets built, the way Eloquent is part of how a Laravel project gets built, no longer something anyone remembers deciding to adopt.

Agencies stop pitching "custom CMS development" as a line item, because the honest answer to "can our client edit their own site" became a solved problem years earlier, and everyone in the room already knows it. The margin an agency used to lose rebuilding this feature per client, worse each time, becomes margin they keep, and the quality their clients receive goes up because nobody is improvising it under deadline anymore.

A developer somewhere writes a blog post — the kind that used to be titled "how I built a custom page builder for my Laravel app" — and instead it's titled "why I stopped building my own." Newer developers read the source, the way people once read Laravel's own source to learn what good PHP looked like, and come away with a clearer idea of what a well-mannered Laravel package is supposed to feel like: restrained, honest about what it does and doesn't do, and utterly unsurprising to work with.

Nobody describes it as "a page builder for Laravel," the same way nobody describes Livewire as "a way to avoid writing JavaScript" anymore — the category label stops being how people talk about it, because it has quietly become the answer, not one option among several. Its indispensability isn't loud. It's the kind you only notice by trying to imagine the ecosystem without it, and finding that hard to do.
