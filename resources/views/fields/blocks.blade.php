@php
    $blocksPath = \Illuminate\Support\Str::after($modelPath, 'editingProps.');
    $items = array_values(array_filter(data_get($editingProps, $blocksPath) ?? [], 'is_array'));
    $registry = app(\WeArePixel\Inlay\Support\BlockRegistry::class);
    $available = $registry->allowedBy($field->allowedBlocks);
    $atLimit = $field->maxBlocks !== null && count($items) >= $field->maxBlocks;
@endphp
<div class="inlay-blocks" data-test="blocks-{{ $field->name }}">
    @include('inlay::fields.partials.label', ['field' => $field])

    <ul class="inlay-blocks__list">
        @foreach ($items as $index => $item)
            @php
                $definition = $registry->hasHandle($item['type'] ?? '') ? $registry->definition($item['type']) : null;
                $detail = $definition ? \WeArePixel\Inlay\Support\SectionInstanceLabel::detail($definition, $item['settings'] ?? []) : null;
            @endphp
            <li class="inlay-blocks__item" wire:key="block-{{ $item['id'] ?? $index }}" data-test="block-item-{{ $item['id'] ?? $index }}">
                <button
                    type="button"
                    class="inlay-blocks__select"
                    wire:click="selectBlock({{ $selectedSectionId }}, @js($item['id'] ?? ''))"
                    data-test="select-block-{{ $item['id'] ?? $index }}"
                >
                    <span class="inlay-tree__label-name">{{ $definition?->name ?? $item['type'] ?? 'Unknown block' }}</span>
                    @if ($detail)
                        <span class="inlay-tree__label-detail">- &ldquo;{{ $detail }}&rdquo;</span>
                    @endif
                </button>
                <span class="inlay-blocks__actions">
                    <button type="button" class="inlay-tree__icon-button" wire:click="moveBlock(@js($blocksPath), @js($item['id'] ?? ''), -1)" @disabled($index === 0) aria-label="Move {{ $definition?->name }} up" data-test="move-block-up-{{ $item['id'] ?? $index }}"><x-inlay::icon name="chevron-down" :size="12" class="inlay-icon--flip" /></button>
                    <button type="button" class="inlay-tree__icon-button" wire:click="moveBlock(@js($blocksPath), @js($item['id'] ?? ''), 1)" @disabled($index === count($items) - 1) aria-label="Move {{ $definition?->name }} down" data-test="move-block-down-{{ $item['id'] ?? $index }}"><x-inlay::icon name="chevron-down" :size="12" /></button>
                    <button type="button" class="inlay-tree__icon-button" wire:click="duplicateBlock(@js($blocksPath), @js($item['id'] ?? ''))" @disabled($atLimit) aria-label="Duplicate {{ $definition?->name }}"><x-inlay::icon name="duplicate" :size="13" /></button>
                    <button type="button" class="inlay-tree__icon-button inlay-tree__icon-button--danger" wire:click="removeBlock(@js($blocksPath), @js($item['id'] ?? ''))" wire:confirm="Remove this block?" aria-label="Remove {{ $definition?->name }}" data-test="remove-block-{{ $item['id'] ?? $index }}"><x-inlay::icon name="trash" :size="13" /></button>
                </span>
            </li>
        @endforeach
    </ul>

    @if ($available === [])
        <p class="inlay-field__help">No blocks are registered for this area.</p>
    @elseif (! $atLimit)
        <details class="inlay-blocks__add">
            <summary class="inlay-button inlay-button--small" data-test="add-block-{{ $field->name }}">+ Add block</summary>
            <div class="inlay-blocks__menu">
                @foreach ($available as $blockType)
                    <button type="button" wire:click="addBlock(@js($blocksPath), @js($blockType->handle))" x-on:click="$el.closest('details').open = false" data-test="add-block-type-{{ $blockType->handle }}">
                        <strong>{{ $blockType->name }}</strong>
                        @if ($blockType->description !== '')
                            <span>{{ $blockType->description }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </details>
    @endif

    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
