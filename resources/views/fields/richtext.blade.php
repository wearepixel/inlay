<div class="inlay-field">
    <label class="inlay-field__label">{{ $field->label }}</label>
    <textarea wire:model.live.debounce.400ms="{{ $modelPath }}" rows="8" class="inlay-textarea"></textarea>
    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
