<div class="inlay-field">
    <label class="inlay-field__label">{{ $field->label }}</label>
    <select wire:model.live="{{ $modelPath }}" class="inlay-select" data-test="setting-{{ $field->name }}">
        @foreach ($field->options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
