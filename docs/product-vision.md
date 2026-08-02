# Product Vision

Date: 2026-08-02
Status: Founding manifesto — read before any future architecture, table, route, or service is discussed.
Revision note: this replaces the first draft of this document, which narrowed the product too far into a marketing-website page builder. The correction below is not cosmetic — it changes the audience, the product category, and the fundamental building block. `docs/product-redirection-plan.md` has been reconciled against it; see that document's reconciliation appendix for exactly what changed as a result.

This document does not describe what the software does. It describes why it should exist at all, and what it must never become. Every future technical decision should be checked against this document, not the other way around.

---

## Why does this project exist?

Every Laravel developer building something real eventually reaches the same moment: a client, a merchandiser, a customer-success team, or a founder needs to change something on a live application — a campaign page, a category's merchandising, a promo banner, an onboarding flow — and there is no good way to let them, without either handing them access to code they shouldn't touch or making a developer the permanent bottleneck for routine changes.

The honest options today are all compromises:

- **WordPress** gives you a real visual editor, at the cost of your entire application. You don't bolt WordPress onto a custom Laravel ecommerce platform — you'd be running two separate applications, bridging data between them, for the sake of letting someone edit a landing page.
- **Statamic** is the closest relative in spirit — Laravel-native, code-first, genuinely well built — but it is a platform for _building the application_. Adopting it means modeling your content inside Statamic's collections and blueprints from the start, not layering safe visual composition onto a custom Laravel application that already exists, with its own domain models, its own frontend, and its own way of doing things.
- **Filament** and **Nova** solve a different, real problem beautifully: full CRUD administration over Eloquent models, for the people who run the business. Neither has, or is trying to have, a concept of a visual canvas for arranging a customer-facing experience. Handing a merchandiser a form to edit a `products` table row is not the same as letting them arrange a campaign landing page built from a `Hero`, a `ProductGrid`, and a `CountdownBanner` that a developer already wrote.
- **Craft CMS** shares WordPress's category problem with better craftsmanship — still not Laravel, still asking a team to model their content inside its own system rather than the one they've already built.
- **Builder.io** is the closest category match — a genuine visual layer for composing existing components — but it's a hosted, JS-framework-first SaaS platform. The structure of your content lives in Builder's cloud, not your database, and its component model assumes a React or Vue codebase, not a Laravel application built on Blade and Livewire.
- **Gutenberg** is WordPress's own attempt at a page-builder-style editor, and it inherits every one of WordPress's category problems, plus a decade of developer frustration with the markup and the editing model it imposes on a PHP application.
- **Rolling your own** is the real competitor. Almost every serious Laravel team eventually builds some version of a slug-to-component registry with a JSON props column, because it's the obviously correct shape. But it's built once, under deadline, for one project, and it never gets the polish, the guardrails, or the safety a real product deserves — because it was never treated as one. It's built to survive one launch, not to be trusted with the next fifty.

Nobody in the Laravel ecosystem has made this good enough to stop rebuilding. That is the gap. This project exists to be the version a team builds once, at the quality of a real product, and never has to build again — not just for marketing sites, but for any developer-built frontend experience a non-developer needs to safely compose or configure inside an application that already exists.

---

## Who is this for?

**The relevant boundary is not "marketing website versus application." The relevant boundary is whether a developer wants to expose safe visual composition of components they've already built.** That boundary cuts across public marketing pages, ecommerce merchandising, authenticated account experiences, and configurable regions inside an application screen alike.

Concretely, this covers experiences like:

- campaign and landing pages
- collection and category merchandising for an ecommerce storefront
- product grids and featured collections backed by real, live application data
- promotion banners and countdown-driven campaigns
- buying guides and editorial content
- store-locator and availability modules
- forms that submit into the host application's own logic
- loyalty and rewards content
- customer-portal surfaces and account dashboards
- onboarding experiences
- authenticated content generally
- any other frontend experience a developer has built and is willing to expose to controlled composition

**The ideal customer is a Laravel developer, team, or agency who has already built (or is building) real Blade or Livewire components for a real application, and wants specific, developer-chosen parts of that application's frontend to be safely composed and configured by someone who isn't a developer** — without giving up ownership of the code, the data, or the frontend they already built.

- **Solo developers** — yes. The person most punished by "can you just change this" requests arriving forever after launch, on a marketing page or inside the app itself.
- **Agencies** — yes, still the primary audience. This is the feature every agency rebuilds per client, worse each time, and it now extends past the marketing site into every custom application they ship.
- **SaaS founders** — yes, and no longer only for the marketing shell. A SaaS product's onboarding flow, its account dashboard's configurable widgets, its in-app promotional surfaces — all genuine candidates, alongside the marketing site.
- **Enterprise teams** — yes, for teams that want to hand specific, bounded frontend composition to a non-developer team (merchandising, content, growth) without handing over arbitrary CRUD access. Not yet for teams that need heavyweight, multi-approval content governance or translation management as a platform.
- **Internal applications** — a genuine but secondary case. An internal tool's dashboard occasionally benefits from letting an ops team rearrange a set of developer-built widgets — the same underlying capability applies — but this is not the product's center of gravity, and building for it explicitly is not a first-release concern.
- **Marketing websites** — still a core, obvious use case. Just no longer the _only_ one, and no longer the lens the whole product is designed through.

**This still does not replace Filament or Nova, and it is important to be precise about why.** Filament and Nova exist to give a developer _full, arbitrary CRUD administration_ over their own Eloquent models — the tool of a developer building internal tooling for people who are trusted with the business's raw data. This project exists to give a developer a way to expose a _narrow, pre-approved, visually composable slice_ of their own frontend to someone who should never have arbitrary data access at all. A merchandiser using this product cannot query the database, cannot edit a model they weren't given a field for, and cannot do anything a developer didn't explicitly allow. Filament and Nova are power tools for people who are supposed to have power. This is a safety-railed tool for people who are supposed to have exactly the amount of power a developer chose to give them, and no more. The two are not competitors — a single application might reasonably run both, for different teams, for different reasons.

**Who should not install this:**

- Anyone who wants a headless content API with no admin UI of its own.
- Anyone who wants a no-code tool that lets a non-developer _invent_ new frontend capability from nothing — every experience this composes was written by a developer first.
- Anyone building a general-purpose internal data-administration tool — reach for Filament or Nova.
- Anyone who needs enterprise content governance (multi-locale workflows, approval chains, compliance audit trails) on day one.

---

## What should this product become?

This is not, first and foremost, a CMS with a page builder attached to it. **It is a visual composition and editing layer for existing Laravel applications.** It may provide CMS-shaped capabilities — pages, routing, drafts, publishing, navigation, media, revisions — but those exist to support developer-built frontend composition, not to define the whole product. A team should never feel like they adopted a CMS. They should feel like they gave specific, chosen parts of their own application a safe way to be rearranged by someone who isn't a developer.

Five years from now, ignoring every current technical limitation: this should be the package a Laravel developer reaches for as reflexively as they reach for Livewire when they need interactivity, or Filament when they need an admin panel — except reached for on projects nobody would call "a CMS site." A custom ecommerce platform, a SaaS product, an internal tool with a configurable dashboard — all plausible places to find it installed, none of them "a website" in the way that would once have implied "you'd use WordPress for that."

If it succeeds, here is what people would say about it:

- "We stopped building this ourselves the day we found it — and not just for the marketing site."
- "It's the only visual-composition tool I've used that didn't feel like smuggling a CMS into a real application."
- "Our merchandising team updates the homepage collections themselves now. Nobody on engineering has to touch it."
- "It's the kind of package where you read the source once and just trust it after that."
- "It doesn't try to be everything. It's really good at the one thing it does."

The reputation to earn is craftsmanship and restraint, in service of a category nobody else has served properly: safe, developer-controlled visual composition _inside_ a real, custom-built Laravel application — not instead of one.

---

## What does it refuse to become?

This list matters more than any feature list. Every future decision gets checked against it.

- **It will never allow visual HTML editing.** An editor arranges and configures components a developer wrote. They never touch markup.
- **It will never allow arbitrary CSS or class editing.** Presentation is the developer's decision, made once, in code.
- **It will never store generated HTML as canonical content.** The database holds structured, typed, queryable data — component identity, configuration, and placement — never a rendered blob.
- **It will never lock a developer in.** No proprietary content format, no format only this package can read, no dependency that can't be cleanly removed.
- **It will never require a proprietary component format.** A component is a real Blade or Livewire class the developer already knows how to write and test.
- **It will never rely on magic.** No behavior a developer can't trace by reading the code.
- **It will never try to replace code.** It composes and configures what a developer built. It does not generate functionality, business logic, or design decisions.
- **It will never let an editor create new business functionality.** Composition is not development. Arranging and configuring existing, developer-approved components is the ceiling.
- **It will never copy business data into page storage merely to display it.** A selected collection, a filtered product set, a customer's booking availability — the underlying domain data stays owned by the host application's own models, resolved live, never duplicated into the builder's own tables.
- **It will never force a specific frontend stack, build tool, or design system on the host application.** The moment it starts dictating how a consuming app builds its frontend, it has stopped being a composition layer and started being a framework.
- **It will never let its own administration interface's styling reach the components it renders.** The admin may look however it needs to look to be a good tool; every component it renders must look exactly as the application that built it intended — Tailwind, Bootstrap, plain CSS, or anything else, indistinguishable from how it renders outside the builder entirely.
- **It will never force the host application to reorganise itself around it.** It installs into an application that already exists, on the application's own terms. It never asks a team to remodel their domain, their routing, or their frontend to fit its conventions.
- **It will never become a general-purpose content-type modeling platform.** It solves _safe composition of developer-built frontend_. It is not trying to become Craft or Contentful.
- **It will never build a plugin or theme marketplace as a business model.**
- **It will never become the place business logic lives.** It records what's composed and configured. It does not decide how the business works.

---

## Core philosophy

> **The governing boundary underneath every principle below: the package owns composition, the application owns presentation.** Component registration, categories, schemas and settings, validation, placement rules, ordering, persistence, drafts, previews, publishing, revisions, permissions, and the administration experience belong to the package. Component markup, frontend CSS, responsive behaviour, accessibility, JavaScript behaviour, frontend assets, business logic, domain models, application data, and the mapping from a structured setting to what it actually looks like belong to the application, always. The package must be fully agnostic about the consuming application's frontend implementation and styling system — Tailwind, Bootstrap, plain CSS, Sass, a custom design system, or anything else renders identically well, because the package never assumes or depends on any of them.

1. **The component is the unit of truth.** A real class a developer wrote, tested, and can `git blame` — never a rendered blob.
2. **Developers define the components, behaviour, and boundaries. Editors compose the experience.** This is the central promise, and every other principle here is a consequence of it.
3. **An editor can never do more than a developer explicitly allowed.** Every editable field, every placement rule, every constraint is a deliberate decision.
4. **Content stays structured, forever.** Configuration is data; markup is code. The two never trade places.
5. **Business data is never duplicated into the builder to display it.** A component resolves what it needs live, from the application it was built for.
6. **Nothing is invented on anyone's behalf.** The package renders and enforces what was built and defined. It generates nothing.
7. **A constraint is a feature.** A guardrail that limits what's possible produces safer, more reliable outcomes than a canvas that permits everything — this matters more, not less, once real application functionality is in scope.
8. **Your data is yours.** It lives in your migrations, in your database, queryable with the tools you already know.
9. **Boring is the goal, not the insult.** Publishing, drafts, and revisions work exactly as a Laravel developer already expects.
10. **It should feel like it could have shipped with Laravel.** Same restraint, same conventions, same instinct for convention over configuration.
11. **An install should look finished, not scaffolded.**
12. **New power comes from writing a new component or a new constraint, never from a hidden setting.**
13. **A developer is never trapped.** No format, dependency, or convention that can't be cleanly walked away from.
14. **It installs into an application. It does not ask the application to reorganise around it.**
15. **Every feature request is measured against one question: does this belong in the developer's own component or constraint definition instead?** Most of the time, the honest answer is yes.
16. **Trust compounds one boring release at a time.** This runs on infrastructure a real business depends on, including in places far more sensitive than a marketing page.
17. **Restraint is where the delight lives.** The calmest interface wins over the most powerful-looking one.
18. **A revision history is a promise, not a feature.** Nothing an editor does should ever be truly, quietly destructive.
19. **It earns its place by disappearing into the host application — never the other way around.**

---

## The emotional experience

If Statamic feels calm, Laravel feels elegant, Linear feels fast, Ray feels joyful, and Forge feels trustworthy — this should feel **composed**. Both meanings at once: the emotional state of someone unhurried and sure of themselves, and the literal act of arranging something with care. Now that the product's reach extends into real application surfaces — an ecommerce collection page, an authenticated account dashboard — that second meaning matters even more: "composed" is also the opposite of "improvised," which is exactly the risk when a client is arranging something wired into real inventory, real customers, or real bookings.

For a developer, the feeling is **relief** — watching a category of chore disappear, and trusting that it disappeared safely, even on the parts of the application that actually matter.

For an editor, the feeling is **quiet competence** — the confidence of someone handed a small, well-organized toolbox where every tool is exactly where they expect it. On an ecommerce collection page, that confidence has to be earned harder than on a marketing page: they should feel just as safe rearranging a `ProductGrid` bound to real inventory as they do editing a heading, because the guardrails around what can be removed, required, or reordered are exactly as considered as the fields themselves.

What that means, concretely, in every surface of the product:

- **Visual design** — unhurried and generous. Real whitespace, not dashboard density. A restrained neutral palette with a single confident accent used only where it means something — never decoration for its own sake.
- **Interaction design** — every action confirms itself quietly. Nothing requires a second guess. A locked or required component should read as obviously, calmly non-negotiable — never as a mysterious button that doesn't work.
- **Typography** — a highly legible, workmanlike interface face, monospace only where it's telling the truth about something technical.
- **Spacing** — closer to a well-set document than a data-dense dashboard.
- **Animation** — minimal, fast, and purposeful. Motion clarifies a state change, never performs.
- **Colour** — mostly neutral. Reserved for meaning: draft vs. published, a warning, a locked or required component, the one confident accent for the primary action on a screen.
- **Language** — plain and first-person-friendly. "Publish," not "Deploy content object." A locked component says why it's locked, not just that it is.
- **Discoverability** — nothing important is three clicks deep. Adding a component feels like opening a small, curated shelf — and on a page with real constraints, the shelf itself is already the right size, because components that don't belong there were never offered.
- **Onboarding** — the first five minutes should feel like the tool already understands the application it was installed into.

Every future UI decision should be checked against one question: does this still feel composed?

---

## What is the fundamental building block?

**Everything rendered by the builder is fundamentally a Component.** One interface, one registration path, one rendering pipeline. That conclusion survives the broadened vision — if anything, it's stronger now: an ecommerce `ProductGrid`, a marketing `Hero`, and a site-wide `MarketingLayout` are all, mechanically, a class a developer wrote that turns configuration into rendered output. Inventing separate registration ceremonies for each would have mirrored CMS taxonomy without a reason rooted in how this system actually works.

But collapsing to one concept does not mean pretending every component behaves identically at runtime, and the correction here matters: **components differ in capability, not in kind.** A component declares what it can do through a small, honest set of optional capabilities, not through which of four historical CMS boxes it was placed in:

- **Placed inside page content** — the default. Most components: a `Hero`, a `ProductGrid`, a `PromoBanner`.
- **Wraps page content** — what the earlier draft of this vision called a "Layout." A component that renders the rest of the composition as its own slot, rather than sitting alongside it. This is a real behavioral difference from an ordinary component — it needs somewhere to put "everything else" — but it does not need its own interface, registry, or registration verb. It needs one declared capability.
- **Accepts child components** — a genuinely new capability worth naming honestly: a component that composes other components inside itself (a two-column layout region, a tabbed group), rather than sitting flat in the page's own list. This is a real, distinct runtime behavior — recursive rendering — and the architecture should support it as a capability from the start, even if the first release's editor doesn't yet offer nested drag-and-drop for it.
- **Requires application context** — a component that needs something the _page's surrounding request_ supplies, beyond its own stored configuration: the collection currently being viewed, the authenticated customer, the booking being managed. This is what used to be gestured at as "a Module" — but the honest distinction was never about registration, it was about whether a component needs context handed to it versus resolving everything itself.
- **Constrained placement** — appears only once, cannot be removed, cannot be reordered — these are not properties of the component's own class at all. They are decisions about a _specific page's_ use of that component, made by whoever defines what that kind of page is allowed to contain. Baking them into the component itself would conflate "what this component is capable of" with "what a particular page is allowed to do with it" — two different questions with two different owners.

That last point is the second correction worth making explicit: **not every constraint belongs on the Component.** A `ProductGrid` component doesn't inherently know it's required on every collection page, or that it can't be removed from one — that's a fact about _collection pages_, not about `ProductGrid` itself. The same component might be optional and freely removable on a marketing landing page and mandatory, locked, and un-reorderable on an ecommerce collection page. That distinction needs its own place to live, separate from the component — a **Page Definition**: a developer-authored, non-rendering class that says what a given kind of page is allowed, required, and forbidden to contain. A Page Definition is not a Component. It renders nothing. It is policy, not presentation, and treating it as a fifth flavor of Component would be exactly the false equivalence this correction is meant to rule out.

So the model is genuinely simple, without being falsely simple: **one Component concept, described by a small set of honest capabilities, plus one separate, non-rendering Page Definition concept that governs what a specific kind of page may do with the components available to it.** Two things, not five — but two things that are actually different in kind, not one thing wearing five labels.

---

## The developer experience

**Day 1.** Install it, register a few Blade components that already exist in the application — a marketing `Hero` alongside a real, data-backed `ProductGrid` — create the first page, and feel something close to surprise: it already looks finished, and the `ProductGrid` renders real inventory in the preview, not a placeholder.

**Week 1.** A merchandiser is rearranging a collection page's featured products without supervision. A marketer is composing a campaign landing page from the same admin. The Slack messages that used to say "can you change the homepage collection" and "can you update the hero text" have both stopped arriving — not because two different systems were built, but because one was.

**Month 6.** A library of components spans marketing content and real application surfaces — collection merchandising, a store locator, a loyalty banner — shared as a convention across projects. Starting a new engagement's "let the client manage this" requirement takes an afternoon, not a sprint, because both the marketing and the application-aware cases were solved once.

**Year 3.** It has become invisible in the way genuinely good infrastructure becomes invisible. New hires are handed the docs and are productive the same day, because the whole thing still fits in someone's head — one Component concept, one Page Definition concept, nothing more to remember.

It avoids becoming cumbersome over that time by discipline: every new capability still arrives as a component a developer writes, or a constraint a developer declares. The package's own surface area is not allowed to grow into a thousand configuration flags. It grows by getting quieter, not louder.

---

## The editor experience

A non-technical editor should experience this as arranging tiles on a board, not as operating software — whether the board is a marketing page or an ecommerce collection page. They should never be confused about what they're allowed to touch: if a field is on the screen, it's safe to change. They should never be confused about what happens when they save: it's immediate, visible, and never truly destructive.

On a page with real guardrails, they should also never be confused about _why_ something is locked, required, or unavailable — a required `ProductGrid` should read as obviously, calmly necessary, not as a mysterious restriction. The ceiling on their power is drawn once, per kind of page, by a developer: full control over arrangement and over every field a developer chose to expose, zero control over anything structural, and — on the pages that need it — zero ability to remove or disable the one component the business actually depends on. That ceiling is a kindness: it's what makes it impossible for them to break something they didn't know they could break, whether that something is a headline or a collection's product listing.

---

## The ecosystem

This is not Horizon, a window onto a subsystem — queues — that already belongs entirely to Laravel core. It is not Pulse, a passive observability layer. It is not Forge, infrastructure and hosting, an entirely different category and business.

The closest philosophical relative is Filament — not because they compete, but because they solve mirrored problems with the same instinct for craft. Filament is the missing administration surface for a Laravel application's own Eloquent models, for the people trusted with the business's raw data. This is the missing composition surface for a Laravel application's own developer-built frontend experiences, for the people who should touch only what a developer chose to expose. A single project might reasonably run both at once — Filament for the internal back office, this for every customer-facing surface a non-developer needs to touch, whether that's a marketing page or a collection's merchandising. Kinship, not overlap.

It should feel like it belongs on the same shelf as Livewire, Filament, and the well-known Spatie packages — built on Livewire rather than around it, happy to sit beside Filament rather than compete with it, never reaching for territory Laravel and its own first-party tools already own.

---

## Naming

"Page Builder" describes a category, and it was already too narrow before this correction — it now actively undersells the product. It implies "a tool for building pages," when the corrected vision is "a tool for safely composing developer-built experiences inside an application that already exists." The earlier shortlist (Lattice, Loom, Draft) has been re-evaluated against that correction, and re-weighted: names that lean toward _content and pattern_ fit the old, narrower vision better than the corrected one, which leans toward _fitting safely into a real, working system_.

- **Lattice** — still strong. A structure that supports and shapes what grows through it, without dictating its exact form — arguably a better fit now than before, since it reads as structural/engineering rather than editorial.
- **Fixture** — new, and the strongest fit for the correction: a fixture is a permanent, fitted part of a working system (an electrical or plumbing fixture), which is precisely what this product is — something installed into and made part of an application that already exists, not a standalone canvas beside it.
- **Inlay** — new: a piece set carefully into a larger surface it was made to fit — a strong, premium-feeling metaphor for "composing something new into an application that already exists," with a genuine craft connotation.
- **Loom** — retained but weaker under the correction. Weaving separate pieces together still works mechanically, but the textile metaphor leans editorial/content in a way that undersells application-aware composition.
- **Draft** — retained but weaker under the correction. The draft/publish pun is genuinely clever, but it describes a CMS-support feature, not the corrected core identity, which is no longer primarily about drafting content.

**Recommendation: Fixture, Lattice, and Inlay**, in that order, as the strongest fits for the corrected vision — with **Fixture** the most honest single-word description of what this product actually does: installs into, and becomes a working part of, an application someone already built. The working package name stays unchanged until trademark, Composer-namespace, and domain availability are checked separately — nothing here commits to a rename.

---

## Mission statement

**One sentence.** Developers define the components, behaviour, and boundaries. Editors compose the experience.

**One paragraph.** This exists because the Laravel ecosystem has never had a proper answer to "can someone who isn't a developer safely change part of this application?" — only compromises borrowed from other ecosystems, or bespoke tools built once, under deadline, that only ever covered the marketing page and never the parts of the application that actually mattered. It gives a developer a way to write real Blade or Livewire components — for a landing page, for a product grid backed by real inventory, for an authenticated account surface — decide exactly what's safe for someone else to configure and arrange, and declare exactly what a given kind of page is and isn't allowed to do with those components. Everything else — composition, drafting, previewing, publishing, and a full history of every change — is handled by software good enough that nobody involved has to build it themselves again. It never generates markup, never invents functionality, never duplicates business data it doesn't own, and never asks the host application to reorganise itself to make room for it.

**One page.** For as long as Laravel developers have built for clients, merchandisers, and founders, there has been a moment where someone who isn't a developer needs to change something real — a campaign page, a collection's featured products, an account dashboard's layout — and the developer has had to choose between compromises: hand the whole stack over to WordPress, adopt a platform that wants to own the application, build something bespoke that only ever covers the marketing page and quietly stays fragile everywhere else, or simply agree to be the person who makes these changes forever. None of those are a solution. They're the least bad option available, chosen under deadline. This exists to end that choice, everywhere it shows up — not just on the homepage. It starts from a simple, non-negotiable premise: a developer writes real components, decides deliberately what's safe to hand over, declares exactly what a given kind of page is and isn't allowed to contain, and everything else is handled by a piece of software nobody has to think about building again. Content and configuration stay structured, and business data stays exactly where it already lived, resolved live rather than copied. The developer never loses control of their code, their data, or their domain. The editor never gains more power than was deliberately given to them, whether they're editing a headline or rearranging a collection page built on real inventory. Every decision this project makes is weighed against a single test: does this feel like it could have shipped with Laravel itself, built with the same restraint and the same refusal to make the developer holding it feel like they compromised. If it succeeds, it will not be remembered as the biggest page builder available to Laravel developers. It will be remembered as the reason "page builder" stopped being the right word for what it actually does.

---

## Elevator pitch

_(Taylor Otwell: "So... what does it do?")_

"You write real Blade or Livewire components exactly like you already do — a hero section, a product grid backed by your real inventory, whatever your application actually needs. We give you an admin screen where someone who isn't a developer can compose those components onto a page, fill in only the fields you decided were safe to hand over, and publish it — with a full version history, and with guardrails you define so they can't remove or break the parts of the page your business actually depends on. Nothing gets generated, no business data gets copied somewhere it doesn't belong, and you never lose ownership of your own code. It's not a CMS bolted onto your app. It's the missing layer that lets you safely hand over composition of the frontend experiences you've already built."

---

## If this project succeeds

Ten years from now, success looks like this, and none of it is measured in revenue or stars.

A developer joining a Laravel team is handed the same set of expectations as they'd be handed for testing, for queues, for authentication — and this is one of them, without anyone needing to justify why, on a project that would never have been called "a CMS site." It's simply part of how a Laravel application gets built when part of its frontend needs to be safely handed to someone who isn't a developer.

Agencies stop pitching "custom content management" as a separate line item for the marketing site and a separate, harder conversation for "letting the client manage the collection pages," because both were the same solved problem years earlier. The margin that used to be lost rebuilding this — worse, twice, once for content and once for commerce — becomes margin kept, and the quality every client receives goes up because nobody is improvising it under deadline.

A developer somewhere writes a blog post — the kind that used to be titled "how I built a custom page builder for my Laravel app" — and instead it's titled "why I stopped building my own, even for the parts of the app that actually mattered." Newer developers read the source the way people once read Laravel's own source to learn what a well-mannered package looks like, and come away with a clearer idea of what "developer owns the code, editor owns the composition" actually means in practice, on a real application, not just a demo.

Nobody describes it as "a page builder for Laravel," the same way nobody describes Livewire as "a way to avoid writing JavaScript" anymore — the category label stops fitting, because it quietly became the answer for something broader than the label ever implied. Its indispensability isn't loud. It's the kind you only notice by trying to imagine building a real, custom Laravel application without it, and finding that harder than you expected.
