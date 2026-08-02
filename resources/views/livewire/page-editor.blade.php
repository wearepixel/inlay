<div>
    <div class="inlay-topbar">
        <div>
            <a href="{{ route('inlay.pages.index') }}" class="inlay-eyebrow" style="text-decoration: none;">&larr; All pages</a>
            <h1>{{ $page->title }}</h1>
        </div>
        <div class="inlay-topbar__actions" x-data="{ published: false }" x-on:page-published.window="published = true; setTimeout(() => published = false, 2000)">
            <span class="inlay-badge inlay-badge--{{ $page->status->value }}">{{ $page->status->value }}</span>
            <span x-show="published" x-cloak style="color: var(--inlay-published); font-size: 12.5px; font-weight: 600;">Published</span>
            <button type="button" wire:click="toggleHistory" class="inlay-button inlay-button--ghost">History</button>
            <button type="button" wire:click="publish" class="inlay-button inlay-button--primary">Publish</button>
        </div>
    </div>

    <div class="inlay-editor">
        <div class="inlay-editor__column">
            <div class="inlay-editor__heading">
                <span class="inlay-eyebrow">Components</span>
                <button type="button" wire:click="openPicker" class="inlay-button inlay-button--small inlay-button--primary">+ Add</button>
            </div>

            <ul class="inlay-tree" x-data="inlaySortableTree" x-ref="list">
                @forelse ($sections as $section)
                    <li
                        data-section-id="{{ $section->id }}"
                        wire:key="section-{{ $section->id }}"
                        wire:click="selectSection({{ $section->id }})"
                        draggable="true"
                        x-on:dragstart="dragStart({{ $section->id }})"
                        x-on:dragover="dragOver($event)"
                        x-on:drop="drop({{ $section->id }})"
                        class="inlay-tree__item {{ $selectedSectionId === $section->id ? 'is-selected' : '' }}"
                    >
                        <span class="inlay-tree__handle">::</span>
                        <span class="inlay-tree__label">{{ $section->handle }}</span>
                        <span class="inlay-tree__actions">
                            <button type="button" wire:click.stop="duplicateSection({{ $section->id }})" class="inlay-tree__icon-button" title="Duplicate">&#10697;</button>
                            <button
                                type="button"
                                wire:click.stop="deleteSection({{ $section->id }})"
                                wire:confirm="Remove this component from the page?"
                                class="inlay-tree__icon-button inlay-tree__icon-button--danger"
                                title="Delete"
                            >&times;</button>
                        </span>
                    </li>
                @empty
                    <li class="inlay-empty-state">No components yet.</li>
                @endforelse
            </ul>

            @if ($showingHistory)
                <p class="inlay-eyebrow" style="margin-top: 20px;">Version history</p>
                <ul class="inlay-history">
                    @forelse ($revisions as $revision)
                        <li class="inlay-history__item">
                            <span>{{ $revision->label ?? $revision->created_at->diffForHumans() }}</span>
                            <button type="button" wire:click="restore({{ $revision->id }})" class="inlay-button inlay-button--small">Restore</button>
                        </li>
                    @empty
                        <li class="inlay-empty-state">No published revisions yet.</li>
                    @endforelse
                </ul>
            @endif
        </div>

        <div class="inlay-editor__column">
            <div class="inlay-editor__heading">
                <span class="inlay-eyebrow">Settings</span>
            </div>

            @if ($selectedSchema)
                @foreach ($selectedSchema->fields() as $field)
                    @continue(! $field->isVisible($editingProps))
                    @include('inlay::fields.' . $field->type->value, [
                        'field' => $field,
                        'modelPath' => "editingProps.{$field->name}",
                    ])
                @endforeach
            @else
                <p class="inlay-inspector__empty">Select a component to edit its settings.</p>
            @endif
        </div>

        <div class="inlay-editor__column">
            <div class="inlay-preview">
                <div class="inlay-preview__toolbar">
                    <span>Preview</span>
                </div>
                <iframe wire:key="preview-{{ $previewVersion }}" src="{{ $previewUrl }}" class="inlay-preview__frame"></iframe>
            </div>
        </div>
    </div>

    @if ($showingPicker)
        <div class="inlay-picker__overlay" x-data="{ query: '' }" wire:click.self="closePicker">
            <div class="inlay-picker__panel">
                <div class="inlay-picker__search-row">
                    <input
                        type="text"
                        x-model="query"
                        x-init="$nextTick(() => $el.focus())"
                        placeholder="Search components…"
                        class="inlay-picker__search"
                    >
                </div>
                <div class="inlay-picker__body">
                    @forelse ($availableComponents as $category => $components)
                        <p class="inlay-eyebrow inlay-picker__category">{{ $category }}</p>
                        <div class="inlay-picker__grid">
                            @foreach ($components as $component)
                                <button
                                    type="button"
                                    wire:click="addSection(@js($component->slug))"
                                    data-search="{{ \Illuminate\Support\Str::lower($component->label.' '.$component->description) }}"
                                    x-show="query === '' || $el.dataset.search.includes(query.toLowerCase())"
                                    class="inlay-picker__card"
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
                    @empty
                        <p class="inlay-empty-state" style="padding: 0 8px;">No components have been registered yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
