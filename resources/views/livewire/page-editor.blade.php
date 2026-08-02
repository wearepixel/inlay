<div>
    <div class="inlay-topbar">
        <div>
            <a href="{{ route('inlay.pages.index') }}" class="inlay-eyebrow" style="text-decoration: none;">&larr; All pages</a>
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
        >
            <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ $page->status->value }}</span>

            <span wire:loading.delay wire:target="editingProps" class="inlay-status inlay-status--saving">Saving&hellip;</span>
            <span x-show="message" x-cloak x-text="message" x-transition.opacity class="inlay-status inlay-status--success" role="status" aria-live="polite"></span>

            <button type="button" wire:click="toggleHistory" data-test="toggle-history" class="inlay-button inlay-button--ghost" aria-expanded="{{ $showingHistory ? 'true' : 'false' }}">History</button>
            <button type="button" wire:click="publish" wire:loading.attr="disabled" wire:target="publish" data-test="publish" class="inlay-button inlay-button--primary">
                <span wire:loading.remove wire:target="publish">Publish</span>
                <span wire:loading wire:target="publish">Publishing&hellip;</span>
            </button>
        </div>
    </div>

    <div class="inlay-editor">
        <div class="inlay-editor__column">
            <div class="inlay-editor__heading">
                <span class="inlay-eyebrow">Components</span>
                <button type="button" wire:click="openPicker" data-test="open-picker" class="inlay-button inlay-button--small inlay-button--primary">+ Add</button>
            </div>

            <ul class="inlay-tree" x-data="inlaySortableTree" x-ref="list" role="listbox" aria-label="Page components">
                @forelse ($sections as $section)
                    <li
                        data-section-id="{{ $section->id }}"
                        data-test="tree-item-{{ $section->id }}"
                        wire:key="section-{{ $section->id }}"
                        wire:click="selectSection({{ $section->id }})"
                        draggable="true"
                        tabindex="0"
                        role="option"
                        aria-selected="{{ $selectedSectionId === $section->id ? 'true' : 'false' }}"
                        x-on:dragstart="dragStart({{ $section->id }})"
                        x-on:dragover="dragOver($event)"
                        x-on:drop="drop({{ $section->id }})"
                        x-on:keydown.enter.prevent="$el.click()"
                        x-on:keydown.down.prevent.stop="focusAdjacentRow({{ $section->id }}, 1)"
                        x-on:keydown.up.prevent.stop="focusAdjacentRow({{ $section->id }}, -1)"
                        x-on:keydown.alt.down.prevent.stop="moveRow({{ $section->id }}, 1)"
                        x-on:keydown.alt.up.prevent.stop="moveRow({{ $section->id }}, -1)"
                        x-on:keydown.delete.prevent="$el.querySelector('[data-test^=delete-]')?.click()"
                        class="inlay-tree__item {{ $selectedSectionId === $section->id ? 'is-selected' : '' }}"
                    >
                        <span class="inlay-tree__handle" data-test="drag-handle-{{ $section->id }}" aria-hidden="true">::</span>
                        <span class="inlay-tree__label">{{ $section->handle }}</span>
                        <span class="inlay-tree__actions">
                            <button type="button" wire:click.stop="duplicateSection({{ $section->id }})" data-test="duplicate-{{ $section->id }}" class="inlay-tree__icon-button" title="Duplicate" aria-label="Duplicate {{ $section->handle }}">&#10697;</button>
                            <button
                                type="button"
                                wire:click.stop="deleteSection({{ $section->id }})"
                                wire:confirm="Remove this component from the page?"
                                data-test="delete-{{ $section->id }}"
                                class="inlay-tree__icon-button inlay-tree__icon-button--danger"
                                title="Delete"
                                aria-label="Delete {{ $section->handle }}"
                            >&times;</button>
                        </span>
                    </li>
                @empty
                    <li class="inlay-empty-state">
                        No components yet. Click <strong>+ Add</strong> to choose one from the picker.
                    </li>
                @endforelse
            </ul>

            @if ($showingHistory)
                <p class="inlay-eyebrow" style="margin-top: 20px;">Version history</p>
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
            @endif
        </div>

        <div class="inlay-editor__column">
            <div class="inlay-editor__heading">
                <span class="inlay-eyebrow">Settings</span>
            </div>

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
                                            <span class="inlay-group-section__chevron" :class="collapsed ? 'is-collapsed' : ''" aria-hidden="true">&#9662;</span>
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
                <p class="inlay-inspector__empty">Select a component on the left to edit its settings.</p>
            @endif
        </div>

        <div class="inlay-editor__column">
            <div class="inlay-preview" x-data="inlayViewportSelector">
                <div class="inlay-preview__toolbar">
                    <span>Preview</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <div class="inlay-viewport-selector" role="group" aria-label="Preview viewport">
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'desktop' ? 'is-active' : ''" x-on:click="select('desktop')" aria-label="Desktop viewport">Desktop</button>
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'tablet' ? 'is-active' : ''" x-on:click="select('tablet')" aria-label="Tablet viewport">Tablet</button>
                            <button type="button" class="inlay-viewport-selector__button" :class="viewport === 'mobile' ? 'is-active' : ''" x-on:click="select('mobile')" aria-label="Mobile viewport">Mobile</button>
                        </div>
                        <button type="button" class="inlay-preview__refresh" wire:click="$refresh" title="Refresh preview" aria-label="Refresh preview">&#8635;</button>
                    </div>
                </div>
                <div class="inlay-preview__viewport" :class="'inlay-preview__viewport--' + viewport">
                    <iframe wire:key="preview-{{ $previewVersion }}" src="{{ $previewUrl }}" class="inlay-preview__frame" data-test="preview-frame" title="Page preview"></iframe>
                </div>
            </div>
        </div>
    </div>

    @if ($showingPicker)
        <div
            class="inlay-picker__overlay"
            x-data="inlayComponentPicker(@js($componentLookup))"
            x-on:keydown.escape.window="$wire.closePicker()"
            x-on:keydown.down.window.prevent="highlightNext(1)"
            x-on:keydown.up.window.prevent="highlightNext(-1)"
            x-on:keydown.enter.window.prevent="chooseHighlighted()"
            wire:click.self="closePicker"
        >
            <div class="inlay-picker__panel" role="dialog" aria-modal="true" aria-label="Add a component">
                <div class="inlay-picker__search-row">
                    <input
                        type="text"
                        x-model="query"
                        x-init="$nextTick(() => $el.focus())"
                        placeholder="Search components…"
                        class="inlay-picker__search"
                        data-test="picker-search"
                        aria-label="Search components"
                    >
                </div>
                <div class="inlay-picker__hint">
                    <span><kbd>&uarr;</kbd><kbd>&darr;</kbd> navigate</span>
                    <span><kbd>&crarr;</kbd> add</span>
                    <span><kbd>Esc</kbd> close</span>
                </div>
                <div class="inlay-picker__body">
                    <template x-if="recentComponents().length > 0 && query === ''">
                        <div>
                            <p class="inlay-eyebrow inlay-picker__recent-title">Recent</p>
                            <div class="inlay-picker__grid">
                                <template x-for="component in recentComponents()" :key="component.slug">
                                    <button
                                        type="button"
                                        class="inlay-picker__card"
                                        x-on:click="recordRecent(component.slug); $wire.addSection(component.slug)"
                                    >
                                        <span class="inlay-picker__card-icon" x-text="component.icon || component.label.charAt(0)"></span>
                                        <span class="inlay-picker__card-title" x-text="component.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    @forelse ($availableComponents as $category => $components)
                        <div x-show="categoryHasMatches($el)">
                            <p class="inlay-eyebrow inlay-picker__category">{{ $category }}</p>
                            <div class="inlay-picker__grid">
                                @foreach ($components as $component)
                                    <button
                                        type="button"
                                        wire:click="addSection(@js($component->slug))"
                                        x-on:click="recordRecent(@js($component->slug))"
                                        data-search="{{ \Illuminate\Support\Str::lower(implode(' ', $component->searchTerms())) }}"
                                        x-show="matches($el.dataset.search)"
                                        data-test="picker-card-{{ $component->slug }}"
                                        class="inlay-picker__card"
                                        :class="highlighted === $el ? 'is-highlighted' : ''"
                                    >
                                        <span class="inlay-picker__card-icon">
                                            @if ($component->thumbnail)
                                                <img src="{{ $component->thumbnail }}" alt="">
                                            @else
                                                {{ $component->icon ?? \Illuminate\Support\Str::substr($component->label, 0, 1) }}
                                            @endif
                                        </span>
                                        <span>
                                            <span class="inlay-picker__card-title" style="display: block;">{{ $component->label }}</span>
                                            @if ($component->description)
                                                <span class="inlay-picker__card-description">{{ $component->description }}</span>
                                            @endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="inlay-empty-state" style="padding: 24px 20px; text-align: center;">
                            No components have been registered yet. Add one to <code>config('inlay.sections')</code> or via <code>Inlay::singleFileComponent()</code>.
                        </p>
                    @endforelse

                    @if ($availableComponents->isNotEmpty())
                        <p class="inlay-picker__no-results" x-show="!hasAnyMatches()">No components match &ldquo;<span x-text="query"></span>&rdquo;.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
