<div class="inlay-field">
    <label class="inlay-field__label">{{ $field->label }}</label>
    <textarea wire:model.live.debounce.400ms="{{ $modelPath }}" rows="4" class="inlay-textarea" data-test="setting-{{ $field->name }}"></textarea>
    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
