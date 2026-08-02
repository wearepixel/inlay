<div class="inlay-field">
    <label class="inlay-field__label">{{ $field->label }}</label>
    <input type="text" wire:model.live.debounce.400ms="{{ $modelPath }}" class="inlay-input" @if ($field->maxLength) maxlength="{{ $field->maxLength }}" @endif>
    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
