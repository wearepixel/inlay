<div class="inlay-field">
    <label class="inlay-checkbox-row">
        <input type="checkbox" wire:model.live="{{ $modelPath }}" data-test="setting-{{ $field->name }}">
        {{ $field->label }}
        @if ($field->badge)
            <span class="inlay-badge inlay-badge--field">{{ $field->badge }}</span>
        @endif
    </label>
    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
