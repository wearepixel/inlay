<div class="inlay-field">
    <label class="inlay-field__label">{{ $field->label }}</label>
    <input type="text" placeholder="Label" wire:model.live.debounce.400ms="{{ $modelPath }}.label" class="inlay-input" style="margin-bottom: 6px;">
    <input type="url" placeholder="URL" wire:model.live.debounce.400ms="{{ $modelPath }}.url" class="inlay-input" style="margin-bottom: 6px;">
    <label class="inlay-checkbox-row">
        <input type="checkbox" wire:model.live="{{ $modelPath }}.openInNewTab">
        Open in new tab
    </label>
    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
