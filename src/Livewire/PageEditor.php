<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use WeArePixel\Inlay\Actions\PublishPage;
use WeArePixel\Inlay\Actions\RestorePageRevision;
use WeArePixel\Inlay\Actions\SetPageAsHomepage;
use WeArePixel\Inlay\InlayManager;
use WeArePixel\Inlay\Livewire\Concerns\InlayAdminComponent;
use WeArePixel\Inlay\Models\Page;
use WeArePixel\Inlay\Models\PageSection;
use WeArePixel\Inlay\Pages\PageDefinitionRegistry;
use WeArePixel\Inlay\Pages\PageDefinitionValidator;
use WeArePixel\Inlay\Pages\PageDefinitionViolationException;
use WeArePixel\Inlay\Pages\ResolvedPageDefinition;
use WeArePixel\Inlay\Pages\RouteConflicts;
use WeArePixel\Inlay\Rules\PagePath;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\BlockRegistry;
use WeArePixel\Inlay\Support\Definition;
use WeArePixel\Inlay\Support\RichTextSanitizer;
use WeArePixel\Inlay\Support\SectionInstanceLabel;
use WeArePixel\Inlay\Support\SectionRegistry;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Support\SettingsCoercer;

final class PageEditor extends Component
{
    use InlayAdminComponent;
    use WithFileUploads;

    public Page $page;

    public ?int $selectedSectionId = null;

    public ?string $selectedBlockId = null;

    public bool $showingHistory = false;

    public bool $showingPicker = false;

    /**
     * Bumped on every action that changes what the page renders, and used
     * only as a query-string cache-buster on the preview iframe's src so a
     * settings change always forces a fresh, real HTTP render of the
     * host application's own frontend rather than a stale cached one -
     * docs/v1-implementation-specification.md §18.
     */
    public int $previewVersion = 0;

    /** @var array<string, mixed> */
    public array $editingProps = [];

    /** @var array<string, mixed> */
    public array $imageUploads = [];

    public string $pageTitle = '';

    public string $pagePath = '';

    public string $pageMetaTitle = '';

    public string $pageMetaDescription = '';

    public bool $pageIsHomepage = false;

    protected function authorizedPage(): Page
    {
        return $this->page;
    }

    public function mount(Page $page): void
    {
        $this->page = $page;
        $this->pageTitle = $page->title;
        $this->pagePath = $page->path ?? '';
        $this->pageMetaTitle = $page->meta_title ?? '';
        $this->pageMetaDescription = $page->meta_description ?? '';
        $this->pageIsHomepage = $page->is_homepage;
    }

    /**
     * The URL the page will publish at, reflecting the Page Settings
     * panel's currently-typed (not-yet-saved) path/homepage state, exactly
     * as PageCreate's own effectiveUrl does - never read from the database
     * directly, since it must update live as an editor types.
     */
    /**
     * The route that answers this page's URL before Inlay can, so the page
     * would never show there.
     */
    #[Computed]
    public function routeConflict(): ?string
    {
        return app(RouteConflicts::class)->routeTaking($this->pageEffectiveUrl());
    }

    #[Computed]
    public function pageEffectiveUrl(): ?string
    {
        $page = new Page([
            'path' => Page::normalizePath($this->pagePath),
            'is_homepage' => $this->pageIsHomepage,
        ]);

        return app(InlayManager::class)->url($page);
    }

    public function savePageSettings(): void
    {
        $this->validate([
            'pageTitle' => ['required', 'string', 'max:255'],
            'pagePath' => ['nullable', 'string', 'max:255', new PagePath(ignorePageId: $this->page->id)],
            'pageMetaTitle' => ['nullable', 'string', 'max:255'],
            'pageMetaDescription' => ['nullable', 'string', 'max:2000'],
        ]);

        $wasHomepage = $this->page->is_homepage;

        $this->page->update([
            'title' => $this->pageTitle,
            'path' => $this->pagePath,
            'meta_title' => $this->pageMetaTitle !== '' ? $this->pageMetaTitle : null,
            'meta_description' => $this->pageMetaDescription !== '' ? $this->pageMetaDescription : null,
        ]);

        if ($this->pageIsHomepage) {
            app(SetPageAsHomepage::class)->execute($this->page);
        } elseif ($wasHomepage) {
            $this->page->update(['is_homepage' => false]);
        }

        $this->page->refresh();
        $this->dispatch('setting-saved');
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function updateOrder(array $orderedIds): void
    {
        // Every update is already scoped to this page's own rows, so a
        // crafted id can never touch another page's sections - but without
        // de-duplicating first, a crafted array padded with a repeated id
        // (e.g. [1, 3, 1, 2]) would survive array_intersect() (which
        // filters by value, not multiplicity) and still issue one UPDATE
        // per element, unbounded by this page's actual section count.
        $ownedIds = $this->page->sections()->orderBy('order')->pluck('id')->all();
        $orderedIds = array_values(array_unique(array_intersect(array_map('intval', $orderedIds), $ownedIds)));

        // A legitimate reorder is always a complete permutation of this
        // page's own sections, exactly what the tree's own drag/keyboard
        // handlers always send (resources/dist/inlay.js's rowIds()) - a
        // crafted subset would silently reassign every remaining section's
        // `order` to a smaller, colliding range instead of leaving the
        // untouched ones alone.
        if (count($orderedIds) !== count($ownedIds)) {
            return;
        }

        // A locked section's position is enforced here, not just hidden
        // behind a disabled drag handle client-side - the whole reorder is
        // rejected (never partially applied) if it would move any locked
        // section at all, since there's no way to know which subset of a
        // crafted request was "the part the UI actually allowed." An
        // unresolved Page Definition also rejects outright - with no rules
        // to check against, denying is the only safe default (matching
        // addSection()'s own fail-closed behaviour for the same case).
        if ($this->reorderWouldMoveALockedSection($ownedIds, $orderedIds)) {
            return;
        }

        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                PageSection::whereKey($id)
                    ->where('page_id', $this->page->id)
                    ->update(['order' => $index]);
            }
        });

        $this->bumpPreview();
    }

    /**
     * @param  array<int, int>  $currentOrderIds
     * @param  array<int, int>  $proposedOrderIds
     */
    private function reorderWouldMoveALockedSection(array $currentOrderIds, array $proposedOrderIds): bool
    {
        $definition = $this->resolvedDefinition();

        // An unresolved definition means there are no rules left to check a
        // reorder against - denying it is the only safe default, the same
        // choice addSection() already makes for this case. Failing open
        // here would let a page whose definition stopped resolving reorder
        // (and, before this fix, even delete) a Section that used to be
        // locked with nothing left to say so.
        if ($definition === null) {
            return true;
        }

        if ($definition->lockedSections === []) {
            return false;
        }

        $lockedIds = PageSection::whereKey($currentOrderIds)
            ->whereIn('handle', $definition->lockedSections)
            ->pluck('id');

        foreach ($lockedIds as $lockedId) {
            if (array_search($lockedId, $currentOrderIds, true) !== array_search($lockedId, $proposedOrderIds, true)) {
                return true;
            }
        }

        return false;
    }

    public function openPicker(): void
    {
        $this->showingPicker = true;
    }

    public function closePicker(): void
    {
        $this->showingPicker = false;
    }

    public function addSection(string $slug): void
    {
        $pageDefinition = $this->resolvedDefinition();

        // Rejected here regardless of what the picker shows client-side -
        // the picker's own filtering (PageEditor::render()) is a courtesy,
        // never the actual boundary. An unresolved Page Definition allows
        // nothing, the same as the picker's own empty state for that case.
        // hasHandle() matters even for the unrestricted default definition,
        // whose allows() is true for any string - without it, a crafted
        // slug that names no real section would reach SectionRegistry::
        // definition() below and throw instead of being quietly rejected.
        if ($pageDefinition === null
            || ! $pageDefinition->allows($slug)
            || ! app(SectionRegistry::class)->hasHandle($slug)) {
            return;
        }

        $definition = app(SectionRegistry::class)->definition($slug);

        $section = $this->page->sections()->create([
            'handle' => $slug,
            'order' => (int) ($this->page->sections()->max('order') + 1),
            // Deliberately sparse: a freshly-added section stores no
            // overrides at all. Its fields resolve entirely from the
            // section's own defaults until an editor changes one -
            // docs/v1-implementation-specification.md §12.
            'settings' => [],
        ]);

        $this->selectedSectionId = $section->id;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($definition, []);
        $this->showingPicker = false;
        $this->bumpPreview();

        // The picker has no way to optimistically switch the contextual
        // sidebar to this brand new section's settings client-side - unlike
        // a tree row click, its id doesn't exist until this method creates
        // it - so the client learns which one to show from this event.
        $this->dispatch('section-selected', id: $section->id);
    }

    public function duplicateSection(int $sectionId): void
    {
        $section = PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->firstOrFail();

        $pageDefinition = $this->resolvedDefinition();

        // A locked section may still be duplicated - the copy is simply
        // locked too, by the same type-level rule - but a section the current
        // definition no longer allows at all must not be extended by
        // duplicating it. hasHandle() also guards against duplicating a
        // section whose own section was deregistered since it was created -
        // checked before creating the duplicate row, not after, so a
        // rejection never leaves a partial write behind.
        // docs/inlay/v1/page-definitions#locked
        if ($pageDefinition === null
            || ! $pageDefinition->allows($section->handle)
            || ! app(SectionRegistry::class)->hasHandle($section->handle)) {
            return;
        }

        $duplicate = $this->page->sections()->create([
            'handle' => $section->handle,
            'order' => (int) ($this->page->sections()->max('order') + 1),
            'settings' => $section->settings,
        ]);

        $definition = app(SectionRegistry::class)->definition($duplicate->handle);

        $this->selectedSectionId = $duplicate->id;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($definition, $duplicate->settings ?? []);
        $this->bumpPreview();

        // Same reasoning as addSection() - the duplicate's id doesn't exist
        // client-side until this method creates it.
        $this->dispatch('section-selected', id: $duplicate->id);
    }

    public function deleteSection(int $sectionId): void
    {
        $section = PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->first();

        if ($section === null) {
            return;
        }

        $pageDefinition = $this->resolvedDefinition();

        // An unresolved definition leaves no rules to check a delete
        // against - denying it outright (rather than defaulting to
        // "allowed", which would let a page whose definition stopped
        // resolving lose a Section that used to be locked or required with
        // nothing left to say so) is the same fail-closed choice
        // addSection() and reorderWouldMoveALockedSection() already make.
        if ($pageDefinition === null) {
            return;
        }

        if ($pageDefinition->isLocked($section->handle)) {
            return;
        }

        // The *final* remaining instance of a required section can't be
        // deleted - v0.1's chosen rule (docs/inlay/v1/page-definitions#required)
        // is to prevent that outright rather than let the page go invalid
        // and rely solely on the publish gate to catch it, while still
        // allowing deletion when another instance of the same section
        // remains. Wrapped in a transaction with a row lock on the
        // sibling instances so two concurrent deletes of the last two
        // instances can't both observe count() === 2 and both proceed.
        if ($pageDefinition->isRequired($section->handle)) {
            $deleted = DB::transaction(function () use ($section, $sectionId) {
                $remaining = $this->page->sections()
                    ->where('handle', $section->handle)
                    ->lockForUpdate()
                    ->count();

                if ($remaining <= 1) {
                    return false;
                }

                PageSection::whereKey($sectionId)->delete();

                return true;
            });

            if (! $deleted) {
                return;
            }
        } else {
            $section->delete();
        }

        if ($this->selectedSectionId === $sectionId) {
            $this->selectedSectionId = null;
            $this->editingProps = [];
            $this->dispatch('selection-cleared');
        }

        $this->bumpPreview();
    }

    public function selectSection(int $sectionId): void
    {
        $section = PageSection::whereKey($sectionId)
            ->where('page_id', $this->page->id)
            ->firstOrFail();

        // A section whose section was deregistered since it was created has
        // no schema left to show settings for - selecting it is a no-op
        // rather than a crash, matching render()'s own placeholder
        // treatment for the same row in the tree. Deleting it is still
        // possible without selecting it first.
        if (! app(SectionRegistry::class)->hasHandle($section->handle)) {
            return;
        }

        $definition = app(SectionRegistry::class)->definition($section->handle);

        $this->selectedSectionId = $sectionId;
        $this->selectedBlockId = null;
        $this->editingProps = app(SectionRenderer::class)->resolveEffectiveRawSettings($definition, $section->settings ?? []);
    }

    public function selectBlock(int $sectionId, string $blockId): void
    {
        if ($this->selectedSectionId !== $sectionId) {
            $this->selectSection($sectionId);
        }

        if ($this->selectedSectionId === $sectionId && $this->locateBlock($blockId) !== null) {
            $this->selectedBlockId = $blockId;
            $this->dispatch('block-selected', id: $blockId);
        }
    }

    public function deselectBlock(): void
    {
        $this->selectedBlockId = null;
        $this->dispatch('block-selected', id: null);
    }

    public function addBlock(string $field, string $type): void
    {
        $setting = $this->blocksField($field);

        if ($setting === null || ! SettingsCoercer::isAllowedBlock($setting, $type) || $this->isFull($setting)) {
            return;
        }

        $schema = app(BlockRegistry::class)->definition($type)->settings;
        $defaults = app(SectionRenderer::class)->resolveSchemaSettings($schema, "block:{$type}", [], raw: true);
        $block = ['id' => SettingsCoercer::newBlockId(), 'type' => $type, 'settings' => $defaults];

        $items = array_values($this->editingProps[$field] ?? []);
        $items[] = $block;
        $this->editingProps[$field] = $items;

        $this->persistSetting($field);
        $this->selectBlock((int) $this->selectedSectionId, $block['id']);
    }

    public function moveBlock(string $field, string $blockId, int $direction): void
    {
        if ($this->blocksField($field) === null) {
            return;
        }

        $items = array_values($this->editingProps[$field] ?? []);
        $from = $this->blockIndex($items, $blockId);

        if ($from === null) {
            return;
        }

        $to = $direction < 0 ? $from - 1 : $from + 1;

        if (! array_key_exists($to, $items)) {
            return;
        }

        [$items[$from], $items[$to]] = [$items[$to], $items[$from]];
        $this->editingProps[$field] = $items;
        $this->persistSetting($field);
    }

    public function duplicateBlock(string $field, string $blockId): void
    {
        $setting = $this->blocksField($field);
        $items = array_values($this->editingProps[$field] ?? []);
        $index = $this->blockIndex($items, $blockId);

        if ($setting === null || $index === null || $this->isFull($setting)) {
            return;
        }

        $copy = [...$items[$index], 'id' => SettingsCoercer::newBlockId()];
        array_splice($items, $index + 1, 0, [$copy]);

        $this->editingProps[$field] = $items;
        $this->persistSetting($field);
        $this->selectBlock((int) $this->selectedSectionId, $copy['id']);
    }

    public function removeBlock(string $field, string $blockId): void
    {
        $items = array_values($this->editingProps[$field] ?? []);
        $index = $this->blockIndex($items, $blockId);

        if ($this->blocksField($field) === null || $index === null) {
            return;
        }

        array_splice($items, $index, 1);
        $this->editingProps[$field] = $items;
        $this->persistSetting($field);

        if ($this->selectedBlockId === $blockId) {
            $this->deselectBlock();
        }
    }

    /**
     * The selected section's blocks field called $field, or null - every
     * block action checks this, since $field arrives from the client.
     */
    private function blocksField(string $field): ?Setting
    {
        return $this->blocksFields()[$field] ?? null;
    }

    /**
     * Every blocks field the selected section declares, keyed by name.
     *
     * @return array<string, Setting>
     */
    private function blocksFields(): array
    {
        if ($this->selectedSectionId === null) {
            return [];
        }

        $section = PageSection::whereKey($this->selectedSectionId)->where('page_id', $this->page->id)->first();

        if ($section === null || ! app(SectionRegistry::class)->hasHandle($section->handle)) {
            return [];
        }

        $fields = [];

        foreach (app(SectionRegistry::class)->definition($section->handle)->settings->fields() as $setting) {
            if ($setting->type === FieldType::Blocks) {
                $fields[$setting->name] = $setting;
            }
        }

        return $fields;
    }

    private function isFull(Setting $blocksField): bool
    {
        $count = count($this->editingProps[$blocksField->name] ?? []);

        return $blocksField->maxBlocks !== null && $count >= $blocksField->maxBlocks;
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function blockIndex(array $items, string $blockId): ?int
    {
        foreach ($items as $index => $item) {
            if (is_array($item) && ($item['id'] ?? null) === $blockId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return array{field: string, index: int}|null
     */
    private function locateBlock(string $blockId): ?array
    {
        foreach (array_keys($this->blocksFields()) as $field) {
            $index = $this->blockIndex(array_values($this->editingProps[$field] ?? []), $blockId);

            if ($index !== null) {
                return ['field' => $field, 'index' => $index];
            }
        }

        return null;
    }

    /**
     * $path is the repeater's path within editingProps - its name for a
     * section's own repeater, deeper for one inside a block.
     */
    public function addRepeaterItem(string $path): void
    {
        $items = data_get($this->editingProps, $path);
        data_set($this->editingProps, $path, [...(is_array($items) ? array_values($items) : []), []]);
        $this->persistSetting(explode('.', $path)[0]);
    }

    public function removeRepeaterItem(string $path, int $index): void
    {
        $items = data_get($this->editingProps, $path);
        $items = is_array($items) ? array_values($items) : [];
        unset($items[$index]);
        data_set($this->editingProps, $path, array_values($items));
        $this->persistSetting(explode('.', $path)[0]);
    }

    /**
     * Livewire reports exactly which nested path changed via $key (e.g.
     * "heading" or "cta.label") - used here to persist only the top-level
     * setting that was actually touched, not the whole editingProps array.
     * Persisting everything on every keystroke would write every field as an
     * explicit override the moment any single field changed, defeating
     * sparse storage entirely.
     */
    public function updatedEditingProps(mixed $value, string $key): void
    {
        $this->persistSetting(explode('.', $key)[0]);
    }

    public function updatedImageUploads(mixed $value, string $key): void
    {
        if (! $value instanceof TemporaryUploadedFile) {
            return;
        }

        $this->validate([
            "imageUploads.{$key}" => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        $path = $value->store('inlay', config('inlay.disk', 'public'));

        data_set($this->editingProps, $key, $path);
        $this->persistSetting(explode('.', $key)[0]);
    }

    public function publish(): void
    {
        try {
            app(PublishPage::class)->execute($this->page, auth()->id());
        } catch (PageDefinitionViolationException) {
            // The violations themselves are already shown as a standing,
            // proactive banner computed in render() below - this dispatch
            // exists only to give immediate "that didn't work" feedback for
            // the click itself, not to carry the violation details again.
            $this->dispatch('publish-blocked');

            return;
        }

        $this->dispatch('page-published');
    }

    public function toggleHistory(): void
    {
        $this->showingHistory = ! $this->showingHistory;

        // The contextual sidebar has no server-rendered "history" state of
        // its own to react to - it decides what to show entirely
        // client-side (see inlayEditorSidebar in inlay.js), so it needs to
        // be told explicitly rather than inferring this from a prop change.
        $this->dispatch('history-toggled', showing: $this->showingHistory);
    }

    public function restore(int $revisionId): void
    {
        $revision = $this->page->revisions()->findOrFail($revisionId);

        $result = app(RestorePageRevision::class)->execute($revision, auth()->id());

        $this->selectedSectionId = null;
        $this->editingProps = [];
        $this->showingHistory = false;
        $this->bumpPreview();

        // $result is null when the restored content violates the Page's
        // *current* Page Definition - it's still in the draft (bumpPreview()
        // above already reflects it), but nothing was published. The
        // standing violation banner (see render()) shows what needs fixing.
        $this->dispatch($result === null ? 'revision-restored-with-violations' : 'revision-restored');
        $this->dispatch('selection-cleared');
    }

    public function render(): View
    {
        $sections = $this->page->sections()->orderBy('order')->get();
        $selectedSection = $this->selectedSectionId
            ? $sections->firstWhere('id', $this->selectedSectionId)
            : null;

        if ($this->selectedSectionId && ! $selectedSection) {
            // The selected section no longer exists - deleted from
            // another tab or session, since this session's own delete and
            // restore() already clear selectedSectionId themselves before
            // reaching here. Fall back to the section tree rather than
            // rendering a settings pane for a section that's gone.
            $this->selectedSectionId = null;
            $this->editingProps = [];
            $this->dispatch('selection-cleared');
        }

        $selectedDefinition = $selectedSection && app(SectionRegistry::class)->hasHandle($selectedSection->handle)
            ? app(SectionRegistry::class)->definition($selectedSection->handle)
            : null;

        $schema = $selectedDefinition?->settings;
        $selectedBlock = $this->selectedBlockForView();
        $sectionTypes = collect(app(SectionRegistry::class)->all());
        $revisions = $this->page->revisions()->get();

        $pageDefinition = $this->resolvedDefinition();
        $requiredSectionHandles = $pageDefinition !== null ? $pageDefinition->requiredSections : [];
        $lockedSectionHandles = $pageDefinition !== null ? $pageDefinition->lockedSections : [];

        // An unresolved Page Definition (its handle no longer matches
        // anything registered) leaves no rules to filter by - the picker
        // shows nothing rather than guessing, and the view surfaces a
        // developer-facing explanation instead of an editor-facing empty
        // state (docs/inlay/v1/page-definitions#editor-guardrails).
        $allowedSections = match (true) {
            $pageDefinition === null => $sectionTypes->filter(fn () => false),
            $pageDefinition->isUnrestricted() => $sectionTypes,
            default => $sectionTypes->filter(fn ($sectionType) => $pageDefinition->allows($sectionType->slug))->values(),
        };

        return $this->adminView('inlay::livewire.page-editor', "{$this->page->title} · Inlay", [
            'sections' => $sections,
            // Display name + a best-guess instance detail (e.g. "Hero" /
            // "Welcome") for every section in the tree - see
            // SectionInstanceLabel. Keyed by section id so the view doesn't
            // need to re-resolve each section's definition itself. A handle
            // no longer registered (the exact case PageDefinitionValidator's
            // unresolved_section violation exists for) falls back to a plain
            // placeholder instead of resolving SectionRegistry::definition()
            // directly, which throws for an unknown handle - the editor
            // must still render so the standing violation banner (and a
            // path to delete the offending section) is reachable at all.
            'sectionLabels' => $sections->mapWithKeys(fn (PageSection $section) => [
                $section->id => app(SectionRegistry::class)->hasHandle($section->handle)
                    ? $this->instanceLabel(
                        app(SectionRegistry::class)->definition($section->handle),
                        $section->settings ?? [],
                    )
                    : ['name' => $section->handle, 'detail' => 'No longer registered'],
            ]),
            'selectedInstance' => $selectedDefinition
                ? $this->instanceLabel($selectedDefinition, $selectedSection->settings ?? [])
                : null,
            'availableSections' => $allowedSections->groupBy(fn ($sectionType) => $sectionType->category),
            // A slug => {label, icon} lookup for the section picker's
            // "recent" row, which tracks recently-added handles client-side
            // (localStorage) and needs enough metadata to render a compact
            // chip for one without a further round trip. Built from the
            // already-filtered list, so a recently-added handle no longer
            // allowed on this Page type simply has no lookup entry - the
            // client-side recentSections() filter already skips those.
            'sectionLookup' => $allowedSections->mapWithKeys(fn ($sectionType) => [
                $sectionType->slug => ['label' => $sectionType->label, 'icon' => $sectionType->icon],
            ]),
            'sectionsExistButNoneAllowed' => $sectionTypes->isNotEmpty() && $allowedSections->isEmpty(),
            'pageDefinitionUnresolved' => $pageDefinition === null,
            'selectedSchema' => $schema,
            'selectedBlock' => $selectedBlock,
            'inspectorTabs' => $this->inspectorTabs($schema),
            'previewUrl' => $this->previewUrl(),
            'revisions' => $revisions,
            'latestRevision' => $revisions->first(),
            'pageDefinition' => $pageDefinition,
            'requiredSectionHandles' => $requiredSectionHandles,
            'lockedSectionHandles' => $lockedSectionHandles,
            'violations' => app(PageDefinitionValidator::class)->validate($this->page),
        ]);
    }

    /**
     * @return array{field: string, index: int, name: string, detail: ?string, tabs: Collection<int, mixed>}|null
     */
    private function selectedBlockForView(): ?array
    {
        if ($this->selectedBlockId === null) {
            return null;
        }

        $location = $this->locateBlock($this->selectedBlockId);

        if ($location === null) {
            return null;
        }

        $item = $this->editingProps[$location['field']][$location['index']];
        $type = $item['type'] ?? null;

        if (! is_string($type) || ! app(BlockRegistry::class)->hasHandle($type)) {
            return null;
        }

        $definition = app(BlockRegistry::class)->definition($type);
        $settings = is_array($item['settings'] ?? null) ? $item['settings'] : [];

        return [
            ...$location,
            'name' => $definition->name,
            'detail' => SectionInstanceLabel::detail($definition, $settings),
            'tabs' => $this->inspectorTabs($definition->settings),
        ];
    }

    /**
     * @param  array<string, mixed>  $storedSettings
     * @return array{name: string, detail: ?string}
     */
    private function instanceLabel(Definition $definition, array $storedSettings): array
    {
        return [
            'name' => $definition->name,
            'detail' => SectionInstanceLabel::detail($definition, $storedSettings),
        ];
    }

    /**
     * The settings inspector's tab > group > field structure, computed once
     * here rather than with nested loops in the view. A section that
     * never calls Settings::tab() ends up with exactly one tab (label
     * null), which the view renders flat with no tab bar - tab navigation
     * only ever appears once a section actually declares more than one.
     *
     * @return Collection<int, array{label: string|null, groups: Collection<int, array{label: string|null, fields: Collection<int, Setting>}>}>
     */
    private function inspectorTabs(?Settings $schema): Collection
    {
        $tabs = collect();

        if (! $schema) {
            return $tabs;
        }

        foreach (collect($schema->fields())->groupBy(fn (Setting $field) => $field->tab ?? '') as $tabKey => $fields) {
            $groups = collect();

            foreach ($fields->groupBy(fn (Setting $field) => $field->group ?? '') as $groupKey => $groupFields) {
                $groups->push([
                    'label' => $this->blankToNull((string) $groupKey),
                    'fields' => $groupFields->values(),
                ]);
            }

            $tabs->push([
                'label' => $this->blankToNull((string) $tabKey),
                'groups' => $groups,
            ]);
        }

        return $tabs;
    }

    /**
     * A native ?string return type, deliberately not narrower - the caller
     * cares only that a blank key means "no tab/group", not that a
     * non-blank one is provably non-empty.
     */
    private function blankToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function previewUrl(): string
    {
        return route('inlay.admin.pages.preview', $this->page).'?v='.$this->previewVersion;
    }

    private function bumpPreview(): void
    {
        $this->previewVersion++;
    }

    private function resolvedDefinition(): ?ResolvedPageDefinition
    {
        return app(PageDefinitionRegistry::class)->resolveForPage($this->page);
    }

    /**
     * Persists exactly one top-level setting as an explicit override, merged
     * into whatever was already stored for this section - never the whole
     * editingProps array. This is the security boundary as much as it is the
     * sparse-storage mechanism: a setting with no matching declaration in the
     * section's resolved definition is silently ignored here, so an
     * editor can never persist a key a developer didn't expose, and
     * coercion always runs unconditionally, regardless of that field's
     * current visibleWhen() state.
     */
    private function persistSetting(string $fieldName): void
    {
        if (! $this->selectedSectionId) {
            return;
        }

        $section = PageSection::whereKey($this->selectedSectionId)
            ->where('page_id', $this->page->id)
            ->first();

        if (! $section) {
            return;
        }

        $definition = app(SectionRegistry::class)->definition($section->handle);
        $setting = $definition->settings->field($fieldName);

        if (! $setting) {
            return;
        }

        $coerced = app(RichTextSanitizer::class)->sanitizeSetting(
            $setting,
            app(SettingsCoercer::class)->coerce($setting, $this->editingProps[$fieldName] ?? null),
        );
        $this->editingProps[$fieldName] = $coerced;

        $stored = $section->settings ?? [];
        $stored[$fieldName] = $coerced;

        $section->update(['settings' => $stored]);
        $this->bumpPreview();
        $this->dispatch('setting-saved');

        if ($this->selectedBlockId !== null && $this->locateBlock($this->selectedBlockId) === null) {
            $this->deselectBlock();
        }
    }
}
