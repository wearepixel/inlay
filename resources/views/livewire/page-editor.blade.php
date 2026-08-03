<div
    class="inlay-editor-page"
    x-data="inlayEditorSidebar(@js($showingHistory ? 'history' : ($selectedSchema ? 'settings' : 'tree')), @js($selectedSectionId))"
    x-on:section-selected.window="showSettings($event.detail.id)"
    x-on:selection-cleared.window="toTree()"
    x-on:history-toggled.window="onHistoryToggled($event.detail.showing)"
    x-on:keydown.escape.window="onEscape()"
>
    <div class="inlay-topbar">
        <div>
            <a href="{{ route('inlay.admin.pages.index') }}" class="inlay-eyebrow" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px;"><x-inlay::icon name="arrow-left" :size="11" /> All pages</a>
            <h1>{{ $page->title }}</h1>
        </div>
        <div
            class="inlay-topbar__actions"
            x-data="{
                message: null,
                announce(message, ms = 2000) {
                    this.message = message;
                    setTimeout(() => { if (this.message === message) { this.message = null; } }, ms);
                },
            }"
            x-on:setting-saved.window="announce('Saved', 1200)"
            x-on:page-published.window="announce('Published successfully', 2500)"
            x-on:revision-restored.window="announce('Revision restored', 2500)"
            x-on:revision-restored-with-violations.window="announce('Restored to draft - not published', 3500)"
            x-on:publish-blocked.window="announce('Publish blocked - see the notice below', 3500)"
        >
            <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ $page->status->value }}</span>

            <span wire:loading.delay wire:target="editingProps" class="inlay-status inlay-status--saving">Saving&hellip;</span>
            <span x-show="message" x-cloak x-text="message" x-transition.opacity class="inlay-status inlay-status--success" role="status" aria-live="polite"></span>

            <button type="button" x-on:click="showPageSettings()" data-test="open-page-settings" class="inlay-button inlay-button--ghost">Page Settings</button>
            <button type="button" wire:click="toggleHistory" data-test="toggle-history" class="inlay-button inlay-button--ghost" aria-expanded="{{ $showingHistory ? 'true' : 'false' }}">History</button>
            <button type="button" x-on:click="$dispatch('open-shortcuts')" data-test="open-shortcuts" class="inlay-button inlay-button--ghost" title="Keyboard shortcuts" aria-label="Keyboard shortcuts">
                <x-inlay::icon name="keyboard" :size="14" />
            </button>
            <button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish" data-test="publish" class="inlay-button inlay-button--primary">
                <span wire:loading.remove wire:target="publish">Publish</span>
                <span wire:loading wire:target="publish">Publishing&hellip;</span>
            </button>
        </div>
    </div>

    @if ($violations !== [])
        <div class="inlay-violation-banner" data-test="violation-banner">
            <span class="inlay-violation-banner__title">This page can't be published yet</span>
            <ul class="inlay-violation-banner__list">
                @foreach ($violations as $violation)
                    <li data-test="violation-{{ $violation->type }}">{{ $violation->message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="inlay-editor" :class="resizing ? 'is-resizing' : ''" :style="'--inlay-sidebar-width: ' + (collapsed ? '0px' : width + 'px')">
        <div class="inlay-mobile-tabs" role="tablist" aria-label="Editor panel">
            <button type="button" role="tab" data-test="mobile-panel-edit" class="inlay-mobile-tabs__tab" :class="mobilePanel === 'edit' ? 'is-active' : ''" :aria-selected="(mobilePanel === 'edit').toString()" x-on:click="mobilePanel = 'edit'">Edit</button>
            <button type="button" role="tab" data-test="mobile-panel-preview" class="inlay-mobile-tabs__tab" :class="mobilePanel === 'preview' ? 'is-active' : ''" :aria-selected="(mobilePanel === 'preview').toString()" x-on:click="mobilePanel = 'preview'">Preview</button>
        </div>

        <p class="inlay-sr-only" role="status" aria-live="polite" x-text="announcement()"></p>

        <div class="inlay-editor__sidebar" :class="(mobilePanel === 'preview' ? 'inlay-mobile-hide ' : '') + (collapsed ? 'is-collapsed' : '')" :inert="collapsed">

            {{-- Block tree (default sidebar state) --}}
            <div x-show="view === 'tree'" x-cloak x-transition.opacity.duration.120ms>
                <div class="inlay-editor__heading">
                    <span class="inlay-eyebrow">Blocks</span>
                    <button type="button" wire:click="openPicker" data-test="open-picker" class="inlay-button inlay-button--small inlay-button--primary">+ Add</button>
                </div>

                <ul class="inlay-tree" x-ref="list" role="listbox" aria-label="Page blocks">
                    @php $sectionHandleCounts = $sections->countBy('handle'); @endphp
                    @forelse ($sections as $section)
                        @php
                            $isLocked = in_array($section->handle, $lockedBlockHandles, true);
                            $isRequired = in_array($section->handle, $requiredBlockHandles, true);
                            $isFinalRequiredInstance = $isRequired && $sectionHandleCounts[$section->handle] <= 1;
                            $canDelete = ! $isLocked && ! $isFinalRequiredInstance;
                        @endphp
                        <li
                            data-section-id="{{ $section->id }}"
                            data-test="tree-item-{{ $section->id }}"
                            wire:key="section-{{ $section->id }}"
                            wire:click="selectSection({{ $section->id }})"
                            x-on:click="showSettings({{ $section->id }})"
                            draggable="{{ $isLocked ? 'false' : 'true' }}"
                            tabindex="0"
                            role="option"
                            aria-selected="{{ $selectedSectionId === $section->id ? 'true' : 'false' }}"
                            @unless ($isLocked)
                                x-on:dragstart="dragStart({{ $section->id }})"
                                x-on:dragover="dragOver($event)"
                                x-on:drop="drop({{ $section->id }})"
                                x-on:keydown.alt.down.prevent.stop="moveRow({{ $section->id }}, 1)"
                                x-on:keydown.alt.up.prevent.stop="moveRow({{ $section->id }}, -1)"
                            @endunless
                            x-on:keydown.enter.prevent="$el.click()"
                            x-on:keydown.down.prevent.stop="focusAdjacentRow({{ $section->id }}, 1)"
                            x-on:keydown.up.prevent.stop="focusAdjacentRow({{ $section->id }}, -1)"
                            x-on:keydown.delete.prevent="$el.querySelector('[data-test^=delete-]')?.click()"
                            class="inlay-tree__item {{ $selectedSectionId === $section->id ? 'is-selected' : '' }}"
                        >
                            <span class="inlay-tree__handle {{ $isLocked ? 'inlay-tree__handle--lock' : '' }}" data-test="drag-handle-{{ $section->id }}" aria-hidden="true">
                                <x-inlay::icon :name="$isLocked ? 'lock' : 'grip'" :size="13" />
                            </span>
                            <span class="inlay-tree__label">
                                <span class="inlay-tree__label-name">{{ $sectionLabels[$section->id]['name'] }}</span>
                                @if ($sectionLabels[$section->id]['detail'])
                                    <span class="inlay-tree__label-detail">- &ldquo;{{ $sectionLabels[$section->id]['detail'] }}&rdquo;</span>
                                @endif
                                @if ($isLocked)
                                    <span class="inlay-tree__indicator" data-test="locked-{{ $section->id }}" title="Locked by {{ $pageDefinition?->label }} - can't be moved or removed"><x-inlay::icon name="lock" :size="9" /> Locked</span>
                                @elseif ($isRequired)
                                    <span class="inlay-tree__indicator" data-test="required-{{ $section->id }}" title="Required by {{ $pageDefinition?->label }}">Required</span>
                                @endif
                            </span>
                            <span class="inlay-tree__actions">
                                <button type="button" wire:click.stop="duplicateSection({{ $section->id }})" data-test="duplicate-{{ $section->id }}" class="inlay-tree__icon-button" title="Duplicate" aria-label="Duplicate {{ $sectionLabels[$section->id]['name'] }}"><x-inlay::icon name="duplicate" :size="14" /></button>
                                @if ($canDelete)
                                    <button
                                        type="button"
                                        wire:click.stop="deleteSection({{ $section->id }})"
                                        wire:confirm="Remove this block from the page?"
                                        data-test="delete-{{ $section->id }}"
                                        class="inlay-tree__icon-button inlay-tree__icon-button--danger"
                                        title="Delete"
                                        aria-label="Delete {{ $sectionLabels[$section->id]['name'] }}"
                                    ><x-inlay::icon name="trash" :size="14" /></button>
                                @else
                                    <button
                                        type="button"
                                        disabled
                                        data-test="delete-disabled-{{ $section->id }}"
                                        class="inlay-tree__icon-button inlay-tree__icon-button--danger"
                                        title="{{ $isLocked ? "Locked by {$pageDefinition?->label} - can't be removed" : "Required by {$pageDefinition?->label} - remove another instance first" }}"
                                        aria-label="{{ $sectionLabels[$section->id]['name'] }} cannot be removed"
                                    ><x-inlay::icon name="trash" :size="14" /></button>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="inlay-empty-state inlay-empty-state--tree">
                            <p class="inlay-empty-state__title">No blocks yet</p>
                            <p>This page has no blocks yet. Add a block to begin composing the page.</p>
                        </li>
                    @endforelse
                </ul>
            </div>

            {{-- Settings inspector (shown while a block is selected) --}}
            <div x-show="view === 'settings'" x-cloak x-transition.opacity.duration.120ms>
                <div class="inlay-editor__heading">
                    <button type="button" class="inlay-back-link" x-on:click="backToTree()" data-test="back-to-blocks">
                        <x-inlay::icon name="arrow-left" :size="12" /> Blocks
                    </button>
                </div>

                @if ($selectedInstance)
                    <h2 class="inlay-instance-heading">
                        {{ $selectedInstance['name'] }}
                        @if ($selectedInstance['detail'])
                            <span class="inlay-instance-heading__detail">- &ldquo;{{ $selectedInstance['detail'] }}&rdquo;</span>
                        @endif
                    </h2>
                @endif

                @if ($selectedSchema)
                    <div x-data="{ activeTab: 0 }">
                        @if ($inspectorTabs->count() > 1)
                            <div class="inlay-tabs" role="tablist">
                                @foreach ($inspectorTabs as $index => $tab)
                                    <button
                                        type="button"
                                        role="tab"
                                        class="inlay-tabs__tab"
                                        :class="activeTab === {{ $index }} ? 'is-active' : ''"
                                        :aria-selected="(activeTab === {{ $index }}).toString()"
                                        x-on:click="activeTab = {{ $index }}"
                                    >{{ $tab['label'] }}</button>
                                @endforeach
                            </div>
                        @endif

                        @foreach ($inspectorTabs as $index => $tab)
                            <div role="tabpanel" @if ($inspectorTabs->count() > 1) x-show="activeTab === {{ $index }}" @endif>
                                @foreach ($tab['groups'] as $group)
                                    @if ($group['label'])
                                        <div class="inlay-group-section" x-data="inlayCollapsibleGroup(@js($group['label']), false)">
                                            <button
                                                type="button"
                                                class="inlay-group-section__header"
                                                x-on:click="toggle()"
                                                :aria-expanded="(!collapsed).toString()"
                                            >
                                                {{ $group['label'] }}
                                                <span class="inlay-group-section__chevron" :class="collapsed ? 'is-collapsed' : ''" aria-hidden="true"><x-inlay::icon name="chevron-down" :size="12" /></span>
                                            </button>
                                            <div class="inlay-group-section__body" x-show="!collapsed" x-transition>
                                                @foreach ($group['fields'] as $field)
                                                    <div @if ($field->visibilityExpression()) x-data x-show="{{ $field->visibilityExpression() }}" x-cloak x-transition @endif>
                                                        @include('inlay::fields.' . $field->type->value, [
                                                            'field' => $field,
                                                            'modelPath' => "editingProps.{$field->name}",
                                                        ])
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        @foreach ($group['fields'] as $field)
                                            <div @if ($field->visibilityExpression()) x-data x-show="{{ $field->visibilityExpression() }}" x-cloak x-transition @endif>
                                                @include('inlay::fields.' . $field->type->value, [
                                                    'field' => $field,
                                                    'modelPath' => "editingProps.{$field->name}",
                                                ])
                                            </div>
                                        @endforeach
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="inlay-inspector__empty">Select a block from the list to edit its settings.</p>
                @endif
            </div>

            {{-- Page settings (title, public path, metadata, homepage, publishing info) --}}
            <div x-show="view === 'page-settings'" x-cloak x-transition.opacity.duration.120ms>
                <div class="inlay-editor__heading">
                    <button type="button" class="inlay-back-link" x-on:click="backToTree()" data-test="back-from-page-settings">
                        <x-inlay::icon name="arrow-left" :size="12" /> Blocks
                    </button>
                </div>

                <p class="inlay-eyebrow">General</p>
                <div class="inlay-field">
                    <label class="inlay-field__label" for="page-settings-title">Title</label>
                    <input id="page-settings-title" type="text" wire:model="pageTitle" class="inlay-input" data-test="page-settings-title">
                    @error('pageTitle') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
                </div>
                <div class="inlay-field">
                    <label class="inlay-field__label">Page type</label>
                    @if ($pageDefinition)
                        <p data-test="page-settings-definition">{{ $pageDefinition->label }}</p>
                        @if ($pageDefinition->description)
                            <p class="inlay-field__help">{{ $pageDefinition->description }}</p>
                        @endif
                    @else
                        <p class="inlay-field__help" style="color: var(--inlay-danger);" data-test="page-settings-definition-unresolved">This page's type is no longer registered. A developer needs to resolve this before publishing.</p>
                    @endif
                    <p class="inlay-field__help">Controls which blocks this page may contain. Cannot be changed after creation.</p>
                </div>
                <div class="inlay-field">
                    <label class="inlay-field__label" for="page-settings-path">Public path</label>
                    <input id="page-settings-path" type="text" wire:model.live.debounce.300ms="pagePath" class="inlay-input" placeholder="about/team" data-test="page-settings-path">
                    <p class="inlay-field__help">
                        Optional. Leave blank for a page with no public URL of its own.
                        @if ($this->pageEffectiveUrl)
                            Publishes at <code data-test="page-settings-effective-url">{{ $this->pageEffectiveUrl }}</code>.
                        @endif
                    </p>
                    @error('pagePath') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
                </div>
                <div class="inlay-field">
                    <label class="inlay-field__label" style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" wire:model="pageIsHomepage" data-test="page-settings-is-homepage">
                        Set as homepage
                    </label>
                    <p class="inlay-field__help">Serves this page at your site's root URL. Replaces the current homepage, if one is already set.</p>
                </div>

                <p class="inlay-eyebrow">Search metadata</p>
                <div class="inlay-field">
                    <label class="inlay-field__label" for="page-settings-meta-title">Meta title</label>
                    <input id="page-settings-meta-title" type="text" wire:model="pageMetaTitle" class="inlay-input" data-test="page-settings-meta-title">
                    <p class="inlay-field__help">Falls back to the title above when left blank.</p>
                    @error('pageMetaTitle') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
                </div>
                <div class="inlay-field">
                    <label class="inlay-field__label" for="page-settings-meta-description">Meta description</label>
                    <textarea id="page-settings-meta-description" wire:model="pageMetaDescription" class="inlay-input" rows="3" data-test="page-settings-meta-description"></textarea>
                    @error('pageMetaDescription') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
                </div>

                <p class="inlay-eyebrow">Publishing</p>
                <p class="inlay-field__help">Status: <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ $page->status->value }}</span></p>
                @if ($latestRevision)
                    <p class="inlay-field__help" data-test="page-settings-last-published">Last published {{ $latestRevision->created_at->diffForHumans() }}{{ $latestRevision->label ? " ({$latestRevision->label})" : '' }}.</p>
                @else
                    <p class="inlay-field__help" data-test="page-settings-last-published">Never published.</p>
                @endif

                <p class="inlay-field__help" data-test="page-settings-live-notice">Public path and homepage status take effect as soon as you save - they aren't part of this page's unpublished block content, so they don't wait for Publish.</p>

                <button type="button" wire:click="savePageSettings" class="inlay-button inlay-button--primary" data-test="save-page-settings">Save page settings</button>
            </div>

            {{-- Version history --}}
            @if ($showingHistory)
                <div x-show="view === 'history'" x-cloak x-transition.opacity.duration.120ms>
                    <div class="inlay-editor__heading">
                        <button type="button" class="inlay-back-link" wire:click="toggleHistory" data-test="close-history">
                            <x-inlay::icon name="arrow-left" :size="12" /> Blocks
                        </button>
                    </div>
                    <p class="inlay-eyebrow">Version history</p>
                    <ul class="inlay-history">
                        @forelse ($revisions as $revision)
                            <li class="inlay-history__item">
                                <span>{{ $revision->label ?? $revision->created_at->diffForHumans() }}</span>
                                <button type="button" wire:click="restore({{ $revision->id }})" data-test="restore-{{ $revision->id }}" class="inlay-button inlay-button--small">Restore</button>
                            </li>
                        @empty
                            <li class="inlay-empty-state">No revisions yet - publish this page to create the first one.</li>
                        @endforelse
                    </ul>
                </div>
            @endif
        </div>

        <div
            class="inlay-editor__resize-handle"
            :class="collapsed ? 'is-collapsed' : ''"
            role="separator"
            aria-orientation="vertical"
            aria-label="Resize the blocks panel"
            tabindex="0"
            data-test="panel-resize-handle"
            x-on:mousedown="startResize($event)"
            x-on:dblclick="toggleCollapsed()"
            x-on:keydown.left.prevent="nudgeWidth(-16)"
            x-on:keydown.right.prevent="nudgeWidth(16)"
        >
            <button
                type="button"
                class="inlay-editor__collapse-toggle"
                data-test="collapse-panel"
                x-on:click.stop="toggleCollapsed()"
                :aria-expanded="(!collapsed).toString()"
                :aria-label="collapsed ? 'Expand panel' : 'Collapse panel'"
                :title="collapsed ? 'Expand panel' : 'Collapse panel'"
            >
                <x-inlay::icon name="chevron-left" :size="12" />
            </button>
        </div>

        <div class="inlay-editor__preview-column" :class="mobilePanel === 'edit' ? 'inlay-mobile-hide' : ''">
            <div class="inlay-preview" x-data="inlayViewportSelector">
                <div class="inlay-preview__toolbar">
                    <div class="inlay-preview__url-bar" title="{{ $this->pageEffectiveUrl ?? "This page has no public path - it isn't reachable at a URL of its own" }}">
                        <x-inlay::icon name="globe" :size="12" />
                        <span class="inlay-preview__url-text">{{ $this->pageEffectiveUrl ?? 'Not published' }}</span>
                    </div>
                    <div class="inlay-preview__toolbar-actions">
                        <div class="inlay-viewport-selector" role="group" aria-label="Preview viewport">
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'desktop' ? 'is-active' : ''" x-on:click="select('desktop')" aria-label="Desktop viewport">Desktop</button>
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'tablet' ? 'is-active' : ''" x-on:click="select('tablet')" aria-label="Tablet viewport">Tablet</button>
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'mobile' ? 'is-active' : ''" x-on:click="select('mobile')" aria-label="Mobile viewport">Mobile</button>
                        </div>
                        <button type="button" class="inlay-preview__refresh" wire:click="$refresh" title="Refresh preview" aria-label="Refresh preview">
                            <x-inlay::icon name="refresh" :size="14" />
                        </button>
                    </div>
                </div>
                <div class="inlay-preview__viewport" :class="'inlay-preview__viewport--' + viewport" wire:loading.class.delay="is-loading">
                    <iframe wire:key="preview-{{ $previewVersion }}" src="{{ $previewUrl }}" class="inlay-preview__frame" data-test="preview-frame" title="Page preview"></iframe>
                    @if ($sections->isEmpty())
                        <div class="inlay-preview__empty" data-test="preview-empty-state">
                            <strong>Nothing here yet</strong>
                            Add a block to see it appear here.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($showingPicker)
        <div
            class="inlay-picker__overlay"
            x-data="inlayBlockPicker(@js($blockLookup))"
            x-on:keydown.escape.window="$wire.closePicker()"
            x-on:keydown.down.window.prevent="highlightNext(1)"
            x-on:keydown.up.window.prevent="highlightNext(-1)"
            x-on:keydown.enter.window.prevent="chooseHighlighted()"
            wire:click.self="closePicker"
        >
            <div
                class="inlay-picker__panel"
                role="dialog"
                aria-modal="true"
                aria-label="Add a block"
                x-transition:enter="inlay-picker__panel-enter"
                x-transition:enter-start="inlay-picker__panel-enter-start"
                x-transition:enter-end="inlay-picker__panel-enter-end"
                x-transition:leave="inlay-picker__panel-leave"
                x-transition:leave-start="inlay-picker__panel-leave-start"
                x-transition:leave-end="inlay-picker__panel-leave-end"
            >
                <div class="inlay-picker__search-row">
                    <input
                        type="text"
                        x-model="query"
                        x-init="$nextTick(() => $el.focus())"
                        placeholder="Search blocks…"
                        class="inlay-picker__search"
                        data-test="picker-search"
                        aria-label="Search blocks"
                    >
                </div>
                <div class="inlay-picker__hint">
                    <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> navigate</span>
                    <span><kbd>&crarr;</kbd> add</span>
                    <span><kbd>Esc</kbd> close</span>
                </div>
                <div class="inlay-picker__body">
                    <template x-if="recentBlocks().length > 0 && query === ''">
                        <div>
                            <p class="inlay-eyebrow inlay-picker__recent-title">Recent</p>
                            <div class="inlay-picker__grid">
                                <template x-for="block in recentBlocks()" :key="block.slug">
                                    <button
                                        type="button"
                                        class="inlay-picker__card"
                                        x-on:click="recordRecent(block.slug); $wire.addSection(block.slug)"
                                    >
                                        <span class="inlay-picker__card-icon" x-text="block.icon || block.label.charAt(0)"></span>
                                        <span class="inlay-picker__card-body">
                                            <span class="inlay-picker__card-title" x-text="block.label"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    @forelse ($availableBlocks as $category => $blocks)
                        <div x-show="categoryHasMatches($el)">
                            <p class="inlay-eyebrow inlay-picker__category">{{ $category }}</p>
                            <div class="inlay-picker__grid">
                                @foreach ($blocks as $block)
                                    <button
                                        type="button"
                                        wire:click="addSection(@js($block->slug))"
                                        x-on:click="recordRecent(@js($block->slug))"
                                        data-search="{{ \Illuminate\Support\Str::lower(implode(' ', $block->searchTerms())) }}"
                                        x-show="matches($el.dataset.search)"
                                        data-test="picker-block-{{ $block->slug }}"
                                        class="inlay-picker__card"
                                        :class="highlighted === $el ? 'is-highlighted' : ''"
                                    >
                                        <span class="inlay-picker__card-icon">
                                            @if ($block->thumbnail)
                                                <img src="{{ $block->thumbnail }}" alt="">
                                            @else
                                                {{ $block->icon ?? \Illuminate\Support\Str::substr($block->label, 0, 1) }}
                                            @endif
                                        </span>
                                        <span class="inlay-picker__card-body">
                                            <span class="inlay-picker__card-title" x-html="highlight(@js($block->label))">{{ $block->label }}</span>
                                            @if ($block->description)
                                                <span class="inlay-picker__card-description">{{ $block->description }}</span>
                                            @endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="inlay-empty-state" style="padding: 24px 20px; text-align: center;" data-test="picker-empty-state">
                            @if ($pageDefinitionUnresolved)
                                This page's type is no longer registered, so Inlay cannot tell which blocks it allows. A developer needs to resolve this before blocks can be added.
                            @elseif ($blocksExistButNoneAllowed)
                                No more blocks are available for this page type.
                            @else
                                No blocks have been registered yet. Add one to <code>config('inlay.blocks')</code> or via <code>Inlay::singleFileBlock()</code>.
                            @endif
                        </p>
                    @endforelse

                    @if ($availableBlocks->isNotEmpty())
                        <p class="inlay-picker__no-results" x-show="!hasAnyMatches()">No blocks match &ldquo;<span x-text="query"></span>&rdquo;.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div x-data="inlayShortcutsHelp" x-on:keydown.window="onKeydown($event)" x-on:open-shortcuts.window="open = true">
        <div
            x-show="open"
            x-cloak
            class="inlay-shortcuts__overlay"
            x-on:keydown.escape.window="close()"
            x-on:click="close()"
            role="dialog"
            aria-modal="true"
            aria-label="Keyboard shortcuts"
            data-test="shortcuts-overlay"
        >
            <div class="inlay-shortcuts__panel" x-on:click.stop>
                <p class="inlay-shortcuts__title">Keyboard shortcuts</p>
                <ul class="inlay-shortcuts__list">
                    <li class="inlay-shortcuts__row">
                        <span>Move focus between blocks</span>
                        <span class="inlay-shortcuts__keys"><kbd>&uarr;</kbd><kbd>&darr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>Open the focused block's settings</span>
                        <span class="inlay-shortcuts__keys"><kbd>&crarr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>Reorder the focused block</span>
                        <span class="inlay-shortcuts__keys"><kbd>Alt</kbd><kbd>&uarr;</kbd><kbd>&darr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>Delete the focused block</span>
                        <span class="inlay-shortcuts__keys"><kbd>Delete</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>Back to the block tree</span>
                        <span class="inlay-shortcuts__keys"><kbd>Esc</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>Resize the blocks panel</span>
                        <span class="inlay-shortcuts__keys"><kbd>&larr;</kbd><kbd>&rarr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>In the block picker: navigate / add</span>
                        <span class="inlay-shortcuts__keys"><kbd>&uarr;</kbd><kbd>&darr;</kbd><kbd>&crarr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>Show this panel</span>
                        <span class="inlay-shortcuts__keys"><kbd>?</kbd></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
