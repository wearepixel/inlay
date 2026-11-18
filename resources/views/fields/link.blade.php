@include('inlay::fields.partials.label', ['field' => $field])
<div class="inlay-field">
    <input type="text" placeholder="Label" wire:model.live.debounce.400ms="{{ $modelPath }}.label" class="inlay-input" style="margin-bottom: 6px;" data-test="setting-{{ $field->name }}-label">
    <input type="url" placeholder="URL" wire:model.live.debounce.400ms="{{ $modelPath }}.url" class="inlay-input" style="margin-bottom: 6px;" data-test="setting-{{ $field->name }}-url">
    <label class="inlay-checkbox-row">
        <input type="checkbox" wire:model.live="{{ $modelPath }}.openInNewTab">
        Open in new tab
    </label>
    @unless ($field->required)
        @php $linkValue = data_get(['editingProps' => $editingProps], $modelPath); @endphp
        @if (filled($linkValue['url'] ?? null) || filled($linkValue['label'] ?? null))
            <button type="button" wire:click="clearField('{{ \Illuminate\Support\Str::after($modelPath, 'editingProps.') }}')" class="inlay-button inlay-button--small inlay-button--ghost inlay-button--danger" data-test="remove-{{ $field->name }}">Remove link</button>
        @endif
    @endunless
    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
