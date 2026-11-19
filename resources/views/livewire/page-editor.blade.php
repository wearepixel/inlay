<div
    class="inlay-editor-page"
    x-data="inlayEditorSidebar(@js($showingHistory ? 'history' : ($selectedSchema ? 'settings' : 'tree')), @js($selectedSectionId), @js(__('inlay::admin.editor.sidebar_announcements')))"
    x-on:section-selected.window="showNewSection($event.detail.id)"
    x-on:selection-cleared.window="toTree()"
    x-on:history-toggled.window="onHistoryToggled($event.detail.showing)"
    x-on:keydown.escape.window="onEscape()"
>
    <div class="inlay-topbar">
        <div>
            <a href="{{ \WeArePixel\Inlay\Facades\Inlay::adminUrl('pages.index') }}" class="inlay-eyebrow" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px;"><x-inlay::icon name="arrow-left" :size="11" /> {{ __('inlay::admin.editor.all_pages') }}</a>
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
            x-on:setting-saved.window="announce(@js(__('inlay::admin.editor.announce_saved')), 1200)"
            x-on:page-published.window="announce(@js(__('inlay::admin.editor.announce_published')), 2500)"
            x-on:revision-restored.window="announce(@js(__('inlay::admin.editor.announce_restored')), 2500)"
            x-on:revision-restored-with-violations.window="announce(@js(__('inlay::admin.editor.announce_restored_with_violations')), 3500)"
            x-on:publish-blocked.window="announce(@js(__('inlay::admin.editor.announce_publish_blocked')), 3500)"
        >
            <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ __('inlay::admin.status.'.$page->status->value) }}</span>

            <span wire:loading.delay wire:target="editingProps" class="inlay-status inlay-status--saving">{{ __('inlay::admin.common.saving') }}</span>
            <span x-show="message" x-cloak x-text="message" x-transition.opacity class="inlay-status inlay-status--success" role="status" aria-live="polite"></span>

            @if (config('inlay.admin.editor_actions'))
                @include(config('inlay.admin.editor_actions'), ['page' => $page])
            @endif

            <button type="button" x-on:click="showPageSettings()" data-test="open-page-settings" class="inlay-button inlay-button--ghost">{{ __('inlay::admin.editor.page_settings') }}</button>
            <button type="button" wire:click="toggleHistory" data-test="toggle-history" class="inlay-button inlay-button--ghost" aria-expanded="{{ $showingHistory ? 'true' : 'false' }}">{{ __('inlay::admin.editor.history') }}</button>
            <button type="button" x-on:click="$dispatch('open-shortcuts')" data-test="open-shortcuts" class="inlay-button inlay-button--ghost" title="{{ __('inlay::admin.editor.keyboard_shortcuts') }}" aria-label="{{ __('inlay::admin.editor.keyboard_shortcuts') }}">
                <x-inlay::icon name="keyboard" :size="14" />
            </button>
            <button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish" data-test="publish" class="inlay-button inlay-button--primary">
                <span wire:loading.remove wire:target="publish">{{ __('inlay::admin.editor.publish') }}</span>
                <span wire:loading wire:target="publish">{{ __('inlay::admin.editor.publishing') }}</span>
            </button>
        </div>
    </div>

    @if ($this->routeConflict)
        @include('inlay::livewire.partials.route-conflict', ['url' => $this->pageEffectiveUrl, 'takenBy' => $this->routeConflict])
    @endif

    @if ($violations !== [])
        <div class="inlay-violation-banner" data-test="violation-banner">
            <span class="inlay-violation-banner__title">{{ __('inlay::admin.editor.violations_title') }}</span>
            <ul class="inlay-violation-banner__list">
                @foreach ($violations as $violation)
                    <li data-test="violation-{{ $violation->type }}">{{ $violation->message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="inlay-editor" :class="resizing ? 'is-resizing' : ''" :style="'--inlay-sidebar-width: ' + (collapsed ? '0px' : width + 'px')">
        <div class="inlay-mobile-tabs" role="tablist" aria-label="{{ __('inlay::admin.editor.panel_label') }}">
            <button type="button" role="tab" data-test="mobile-panel-edit" class="inlay-mobile-tabs__tab" :class="mobilePanel === 'edit' ? 'is-active' : ''" :aria-selected="(mobilePanel === 'edit').toString()" x-on:click="mobilePanel = 'edit'">{{ __('inlay::admin.editor.panel_edit') }}</button>
            <button type="button" role="tab" data-test="mobile-panel-preview" class="inlay-mobile-tabs__tab" :class="mobilePanel === 'preview' ? 'is-active' : ''" :aria-selected="(mobilePanel === 'preview').toString()" x-on:click="mobilePanel = 'preview'">{{ __('inlay::admin.editor.panel_preview') }}</button>
        </div>

        <p class="inlay-sr-only" role="status" aria-live="polite" x-text="announcement()"></p>

        <div class="inlay-editor__sidebar" :class="(mobilePanel === 'preview' ? 'inlay-mobile-hide ' : '') + (collapsed ? 'is-collapsed' : '')" :inert="collapsed">

            {{-- Section tree (default sidebar state) --}}
            <div x-show="view === 'tree'" x-cloak x-transition.opacity.duration.120ms>
                <div class="inlay-editor__heading">
                    <span class="inlay-eyebrow">{{ __('inlay::admin.sections.heading') }}</span>
                    <button type="button" wire:click="openPicker" data-test="open-picker" class="inlay-button inlay-button--small inlay-button--primary">{{ __('inlay::admin.sections.add') }}</button>
                </div>

                <ul class="inlay-tree" x-ref="list" role="listbox" aria-label="{{ __('inlay::admin.sections.list_label') }}" x-on:dragover="dragOver($event)" x-on:drop.prevent="drop()">
                    @php $sectionHandleCounts = $sections->countBy('handle'); @endphp
                    @forelse ($sections as $section)
                        @php
                            $isLocked = in_array($section->handle, $lockedSectionHandles, true);
                            $isRequired = in_array($section->handle, $requiredSectionHandles, true);
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
                                x-on:dragstart="dragStart($event, {{ $section->id }})"
                                x-on:dragend="dragEnd()"
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
                                    <span class="inlay-tree__indicator" data-test="locked-{{ $section->id }}" title="{{ __('inlay::admin.sections.locked_title', ['definition' => $pageDefinition?->label]) }}"><x-inlay::icon name="lock" :size="9" /> {{ __('inlay::admin.sections.locked') }}</span>
                                @elseif ($isRequired)
                                    <span class="inlay-tree__indicator" data-test="required-{{ $section->id }}" title="{{ __('inlay::admin.sections.required_title', ['definition' => $pageDefinition?->label]) }}">{{ __('inlay::admin.sections.required') }}</span>
                                @endif
                            </span>
                            <span class="inlay-tree__actions">
                                <button type="button" wire:click.stop="duplicateSection({{ $section->id }})" data-test="duplicate-{{ $section->id }}" class="inlay-tree__icon-button" title="{{ __('inlay::admin.sections.duplicate') }}" aria-label="{{ __('inlay::admin.sections.duplicate_label', ['section' => $sectionLabels[$section->id]['name']]) }}"><x-inlay::icon name="duplicate" :size="14" /></button>
                                @if ($canDelete)
                                    <button
                                        type="button"
                                        wire:click.stop="deleteSection({{ $section->id }})"
                                        wire:confirm="{{ __('inlay::admin.sections.delete_confirm') }}"
                                        data-test="delete-{{ $section->id }}"
                                        class="inlay-tree__icon-button inlay-tree__icon-button--danger"
                                        title="{{ __('inlay::admin.sections.delete') }}"
                                        aria-label="{{ __('inlay::admin.sections.delete_label', ['section' => $sectionLabels[$section->id]['name']]) }}"
                                    ><x-inlay::icon name="trash" :size="14" /></button>
                                @else
                                    <button
                                        type="button"
                                        disabled
                                        data-test="delete-disabled-{{ $section->id }}"
                                        class="inlay-tree__icon-button inlay-tree__icon-button--danger"
                                        title="{{ $isLocked ? __('inlay::admin.sections.locked_cannot_remove', ['definition' => $pageDefinition?->label]) : __('inlay::admin.sections.required_cannot_remove', ['definition' => $pageDefinition?->label]) }}"
                                        aria-label="{{ __('inlay::admin.sections.cannot_remove_label', ['section' => $sectionLabels[$section->id]['name']]) }}"
                                    ><x-inlay::icon name="trash" :size="14" /></button>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="inlay-empty-state inlay-empty-state--tree">
                            <p class="inlay-empty-state__title">{{ __('inlay::admin.sections.empty_title') }}</p>
                            <p>{{ __('inlay::admin.sections.empty') }}</p>
                        </li>
                    @endforelse
                </ul>
            </div>

            {{-- Settings inspector (shown while a section is selected) --}}
            <div x-show="view === 'settings'" x-cloak x-transition.opacity.duration.120ms>
                <div class="inlay-editor__heading">
                    <button type="button" class="inlay-back-link" x-on:click="backToTree()" data-test="back-to-sections">
                        <x-inlay::icon name="arrow-left" :size="12" /> {{ __('inlay::admin.sections.back') }}
                    </button>
                </div>

                @if ($selectedInstance && ! $selectedBlock)
                    <h2 class="inlay-instance-heading">
                        {{ $selectedInstance['name'] }}
                        @if ($selectedInstance['detail'])
                            <span class="inlay-instance-heading__detail">- &ldquo;{{ $selectedInstance['detail'] }}&rdquo;</span>
                        @endif
                    </h2>
                @endif

                @if ($selectedBlock)
                    <div class="inlay-block-inspector" data-test="block-inspector">
                        <button type="button" class="inlay-back-link" wire:click="deselectBlock" data-test="back-to-section">
                            <x-inlay::icon name="arrow-left" :size="12" /> {{ $selectedInstance['name'] ?? __('inlay::admin.sections.fallback_name') }}
                        </button>
                        <h3 class="inlay-instance-heading inlay-instance-heading--block">
                            {{ $selectedBlock['name'] }}
                            @if ($selectedBlock['detail'])
                                <span class="inlay-instance-heading__detail">- &ldquo;{{ $selectedBlock['detail'] }}&rdquo;</span>
                            @endif
                        </h3>
                        @include('inlay::livewire.partials.inspector', [
                            'inspectorTabs' => $selectedBlock['tabs'],
                            'inspectorKey' => $selectedSectionId.'-'.$selectedBlockId,
                            'pathPrefix' => "editingProps.{$selectedBlock['field']}.{$selectedBlock['index']}.settings.",
                            'visibilityScope' => "\$wire.editingProps['{$selectedBlock['field']}'][{$selectedBlock['index']}]['settings']",
                        ])
                    </div>
                @elseif ($selectedSchema)
                    @include('inlay::livewire.partials.inspector', [
                        'inspectorTabs' => $inspectorTabs,
                        'inspectorKey' => (string) $selectedSectionId,
                        'pathPrefix' => 'editingProps.',
                        'visibilityScope' => '$wire.editingProps',
                    ])
                @else
                    <p class="inlay-inspector__empty">{{ __('inlay::admin.sections.select_prompt') }}</p>
                @endif
            </div>

            {{-- Page settings (title, public path, metadata, homepage, publishing info) --}}
            <div x-show="view === 'page-settings'" x-cloak x-transition.opacity.duration.120ms>
                <div class="inlay-editor__heading">
                    <button type="button" class="inlay-back-link" x-on:click="backToTree()" data-test="back-from-page-settings">
                        <x-inlay::icon name="arrow-left" :size="12" /> {{ __('inlay::admin.sections.back') }}
                    </button>
                </div>

                <p class="inlay-eyebrow">{{ __('inlay::admin.page_settings.general') }}</p>
                <div class="inlay-field">
                    <label class="inlay-field__label" for="page-settings-title">{{ __('inlay::admin.page_fields.title') }}</label>
                    <input id="page-settings-title" type="text" wire:model="pageTitle" class="inlay-input" data-test="page-settings-title">
                    @error('pageTitle') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
                </div>
                <div class="inlay-field">
                    <label class="inlay-field__label">{{ __('inlay::admin.page_fields.page_type') }}</label>
                    @if ($pageDefinition)
                        <p data-test="page-settings-definition">{{ $pageDefinition->label }}</p>
                        @if ($pageDefinition->description)
                            <p class="inlay-field__help">{{ $pageDefinition->description }}</p>
                        @endif
                    @else
                        <p class="inlay-field__help" style="color: var(--inlay-danger);" data-test="page-settings-definition-unresolved">{{ __('inlay::admin.page_settings.type_unresolved') }}</p>
                    @endif
                    <p class="inlay-field__help">{{ __('inlay::admin.page_settings.page_type_help') }}</p>
                </div>
                <div class="inlay-field">
                    <label class="inlay-field__label" for="page-settings-path">{{ __('inlay::admin.page_fields.public_path') }}</label>
                    <input id="page-settings-path" type="text" wire:model.live.debounce.300ms="pagePath" class="inlay-input" placeholder="{{ __('inlay::admin.page_fields.path_placeholder') }}" data-test="page-settings-path">
                    <p class="inlay-field__help">
                        {{ __('inlay::admin.page_settings.path_help') }}
                        @if ($this->pageEffectiveUrl)
                            {!! __('inlay::admin.page_settings.publishes_at', ['url' => '<code data-test="page-settings-effective-url">'.e($this->pageEffectiveUrl).'</code>']) !!}
                        @endif
                    </p>
                    @error('pagePath') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
                </div>
                <div class="inlay-field">
                    <label class="inlay-field__label" style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" wire:model="pageIsHomepage" data-test="page-settings-is-homepage">
                        {{ __('inlay::admin.page_fields.set_as_homepage') }}
                    </label>
                    <p class="inlay-field__help">{{ __('inlay::admin.page_settings.homepage_help') }}</p>
                </div>

                <p class="inlay-eyebrow">{{ __('inlay::admin.page_settings.search_metadata') }}</p>
                <div class="inlay-field">
                    <label class="inlay-field__label" for="page-settings-meta-title">{{ __('inlay::admin.page_fields.meta_title') }}</label>
                    <input id="page-settings-meta-title" type="text" wire:model="pageMetaTitle" class="inlay-input" data-test="page-settings-meta-title">
                    <p class="inlay-field__help">{{ __('inlay::admin.page_settings.meta_title_help') }}</p>
                    @error('pageMetaTitle') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
                </div>
                <div class="inlay-field">
                    <label class="inlay-field__label" for="page-settings-meta-description">{{ __('inlay::admin.page_fields.meta_description') }}</label>
                    <textarea id="page-settings-meta-description" wire:model="pageMetaDescription" class="inlay-input" rows="3" data-test="page-settings-meta-description"></textarea>
                    @error('pageMetaDescription') <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p> @enderror
                </div>

                <p class="inlay-eyebrow">{{ __('inlay::admin.page_settings.publishing') }}</p>
                <p class="inlay-field__help">{{ __('inlay::admin.page_settings.status') }} <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ __('inlay::admin.status.'.$page->status->value) }}</span></p>
                @if ($latestRevision)
                    <p class="inlay-field__help" data-test="page-settings-last-published">{{ $latestRevision->label
                        ? __('inlay::admin.page_settings.last_published_labelled', ['time' => $latestRevision->created_at->diffForHumans(), 'label' => $latestRevision->label])
                        : __('inlay::admin.page_settings.last_published', ['time' => $latestRevision->created_at->diffForHumans()]) }}</p>
                @else
                    <p class="inlay-field__help" data-test="page-settings-last-published">{{ __('inlay::admin.page_settings.never_published') }}</p>
                @endif

                <p class="inlay-field__help" data-test="page-settings-live-notice">{{ __('inlay::admin.page_settings.live_notice') }}</p>

                <button type="button" wire:click="savePageSettings" class="inlay-button inlay-button--primary" data-test="save-page-settings">{{ __('inlay::admin.page_settings.save') }}</button>
            </div>

            {{-- Version history --}}
            @if ($showingHistory)
                <div x-show="view === 'history'" x-cloak x-transition.opacity.duration.120ms>
                    <div class="inlay-editor__heading">
                        <button type="button" class="inlay-back-link" wire:click="toggleHistory" data-test="close-history">
                            <x-inlay::icon name="arrow-left" :size="12" /> {{ __('inlay::admin.sections.back') }}
                        </button>
                    </div>
                    <p class="inlay-eyebrow">{{ __('inlay::admin.history.heading') }}</p>
                    <ul class="inlay-history">
                        @forelse ($revisions as $revision)
                            <li class="inlay-history__item">
                                <span>{{ $revision->label ?? $revision->created_at->diffForHumans() }}</span>
                                <button type="button" wire:click="restore({{ $revision->id }})" data-test="restore-{{ $revision->id }}" class="inlay-button inlay-button--small">{{ __('inlay::admin.history.restore') }}</button>
                            </li>
                        @empty
                            <li class="inlay-empty-state">{{ __('inlay::admin.history.empty') }}</li>
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
            aria-label="{{ __('inlay::admin.editor.resize_panel') }}"
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
                :aria-label="collapsed ? @js(__('inlay::admin.editor.expand_panel')) : @js(__('inlay::admin.editor.collapse_panel'))"
                :title="collapsed ? @js(__('inlay::admin.editor.expand_panel')) : @js(__('inlay::admin.editor.collapse_panel'))"
            >
                <x-inlay::icon name="chevron-left" :size="12" />
            </button>
        </div>

        <div class="inlay-editor__preview-column" :class="mobilePanel === 'edit' ? 'inlay-mobile-hide' : ''">
            <div class="inlay-preview" x-data="inlayViewportSelector">
                <div class="inlay-preview__toolbar">
                    <div class="inlay-preview__url-bar" title="{{ $this->pageEffectiveUrl ?? __('inlay::admin.preview.no_public_path') }}">
                        <x-inlay::icon name="globe" :size="12" />
                        <span class="inlay-preview__url-text">{{ $this->pageEffectiveUrl ?? __('inlay::admin.preview.not_published') }}</span>
                    </div>
                    <div class="inlay-preview__toolbar-actions">
                        <div class="inlay-viewport-selector" role="group" aria-label="{{ __('inlay::admin.preview.viewport_label') }}">
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'desktop' ? 'is-active' : ''" x-on:click="select('desktop')" aria-label="{{ __('inlay::admin.preview.desktop_label') }}">{{ __('inlay::admin.preview.desktop') }}</button>
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'tablet' ? 'is-active' : ''" x-on:click="select('tablet')" aria-label="{{ __('inlay::admin.preview.tablet_label') }}">{{ __('inlay::admin.preview.tablet') }}</button>
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'mobile' ? 'is-active' : ''" x-on:click="select('mobile')" aria-label="{{ __('inlay::admin.preview.mobile_label') }}">{{ __('inlay::admin.preview.mobile') }}</button>
                        </div>
                        <button type="button" class="inlay-preview__refresh" wire:click="$refresh" title="{{ __('inlay::admin.preview.refresh') }}" aria-label="{{ __('inlay::admin.preview.refresh') }}">
                            <x-inlay::icon name="refresh" :size="14" />
                        </button>
                    </div>
                </div>
                <div class="inlay-preview__viewport" :class="'inlay-preview__viewport--' + viewport" wire:loading.class.delay="is-loading">
                    <iframe wire:key="preview-{{ $previewVersion }}" src="{{ $previewUrl }}" class="inlay-preview__frame" data-test="preview-frame" title="{{ __('inlay::admin.preview.frame_title') }}"></iframe>
                    @if ($sections->isEmpty())
                        <div class="inlay-preview__empty" data-test="preview-empty-state">
                            <strong>{{ __('inlay::admin.preview.empty_title') }}</strong>
                            {{ __('inlay::admin.preview.empty') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($showingPicker)
        <div
            class="inlay-picker__overlay"
            x-data="inlaySectionPicker(@js($sectionLookup))"
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
                aria-label="{{ __('inlay::admin.picker.label') }}"
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
                        placeholder="{{ __('inlay::admin.picker.search_placeholder') }}"
                        class="inlay-picker__search"
                        data-test="picker-search"
                        aria-label="{{ __('inlay::admin.picker.search_label') }}"
                    >
                </div>
                <div class="inlay-picker__hint">
                    <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> {{ __('inlay::admin.picker.hint_navigate') }}</span>
                    <span><kbd>&crarr;</kbd> {{ __('inlay::admin.picker.hint_add') }}</span>
                    <span><kbd>{{ __('inlay::admin.keys.esc') }}</kbd> {{ __('inlay::admin.picker.hint_close') }}</span>
                </div>
                <div class="inlay-picker__body">
                    <template x-if="recentSections().length > 0 && query === ''">
                        <div>
                            <p class="inlay-eyebrow inlay-picker__recent-title">{{ __('inlay::admin.picker.recent') }}</p>
                            <div class="inlay-picker__grid">
                                <template x-for="section in recentSections()" :key="section.slug">
                                    <button
                                        type="button"
                                        class="inlay-picker__card"
                                        x-on:click="recordRecent(section.slug); $wire.addSection(section.slug)"
                                    >
                                        <span class="inlay-picker__card-icon" x-text="section.icon || section.label.charAt(0)"></span>
                                        <span class="inlay-picker__card-body">
                                            <span class="inlay-picker__card-title" x-text="section.label"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    @forelse ($availableSections as $category => $sectionTypes)
                        <div x-show="categoryHasMatches($el)">
                            <p class="inlay-eyebrow inlay-picker__category">{{ $category }}</p>
                            <div class="inlay-picker__grid">
                                @foreach ($sectionTypes as $sectionType)
                                    <button
                                        type="button"
                                        wire:click="addSection(@js($sectionType->slug))"
                                        x-on:click="recordRecent(@js($sectionType->slug))"
                                        data-search="{{ \Illuminate\Support\Str::lower(implode(' ', $sectionType->searchTerms())) }}"
                                        x-show="matches($el.dataset.search)"
                                        data-test="picker-section-{{ $sectionType->slug }}"
                                        class="inlay-picker__card"
                                        :class="highlighted === $el ? 'is-highlighted' : ''"
                                    >
                                        <span class="inlay-picker__card-icon">
                                            @if ($sectionType->thumbnail)
                                                <img src="{{ $sectionType->thumbnail }}" alt="">
                                            @else
                                                {{ $sectionType->icon ?? \Illuminate\Support\Str::substr($sectionType->label, 0, 1) }}
                                            @endif
                                        </span>
                                        <span class="inlay-picker__card-body">
                                            <span class="inlay-picker__card-title" x-html="highlight(@js($sectionType->label))">{{ $sectionType->label }}</span>
                                            @if ($sectionType->description)
                                                <span class="inlay-picker__card-description">{{ $sectionType->description }}</span>
                                            @endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="inlay-empty-state" style="padding: 24px 20px; text-align: center;" data-test="picker-empty-state">
                            @if ($pageDefinitionUnresolved)
                                {{ __('inlay::admin.picker.definition_unresolved') }}
                            @elseif ($sectionsExistButNoneAllowed)
                                {{ __('inlay::admin.picker.none_allowed') }}
                            @else
                                {!! __('inlay::admin.picker.none_registered', ['config' => "<code>config('inlay.sections')</code>", 'method' => '<code>Inlay::singleFileSection()</code>']) !!}
                            @endif
                        </p>
                    @endforelse

                    @if ($availableSections->isNotEmpty())
                        <p class="inlay-picker__no-results" x-show="!hasAnyMatches()">{!! __('inlay::admin.picker.no_results', ['query' => '<span x-text="query"></span>']) !!}</p>
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
            aria-label="{{ __('inlay::admin.shortcuts.title') }}"
            data-test="shortcuts-overlay"
        >
            <div class="inlay-shortcuts__panel" x-on:click.stop>
                <p class="inlay-shortcuts__title">{{ __('inlay::admin.shortcuts.title') }}</p>
                <ul class="inlay-shortcuts__list">
                    <li class="inlay-shortcuts__row">
                        <span>{{ __('inlay::admin.shortcuts.focus_sections') }}</span>
                        <span class="inlay-shortcuts__keys"><kbd>&uarr;</kbd><kbd>&darr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>{{ __('inlay::admin.shortcuts.open_settings') }}</span>
                        <span class="inlay-shortcuts__keys"><kbd>&crarr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>{{ __('inlay::admin.shortcuts.reorder') }}</span>
                        <span class="inlay-shortcuts__keys"><kbd>{{ __('inlay::admin.keys.alt') }}</kbd><kbd>&uarr;</kbd><kbd>&darr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>{{ __('inlay::admin.shortcuts.delete') }}</span>
                        <span class="inlay-shortcuts__keys"><kbd>{{ __('inlay::admin.keys.delete') }}</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>{{ __('inlay::admin.shortcuts.back') }}</span>
                        <span class="inlay-shortcuts__keys"><kbd>{{ __('inlay::admin.keys.esc') }}</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>{{ __('inlay::admin.shortcuts.resize') }}</span>
                        <span class="inlay-shortcuts__keys"><kbd>&larr;</kbd><kbd>&rarr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>{{ __('inlay::admin.shortcuts.picker') }}</span>
                        <span class="inlay-shortcuts__keys"><kbd>&uarr;</kbd><kbd>&darr;</kbd><kbd>&crarr;</kbd></span>
                    </li>
                    <li class="inlay-shortcuts__row">
                        <span>{{ __('inlay::admin.shortcuts.show_panel') }}</span>
                        <span class="inlay-shortcuts__keys"><kbd>?</kbd></span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
