@include('inlay::fields.partials.label', ['field' => $field])
@php
    $selectCurrentValue = data_get(['editingProps' => $editingProps], $modelPath);
    $selectDescription = null;

    if ($field->enumClass && $selectCurrentValue !== null) {
        $selectCase = ($field->enumClass)::tryFrom($selectCurrentValue);

        if ($selectCase instanceof \WearePixel\Inlay\Support\DescribesSettingOptions) {
            $selectDescription = $selectCase->optionDescription();
        }
    }
@endphp
<div class="inlay-field">
    <select wire:model.live="{{ $modelPath }}" class="inlay-select" data-test="setting-{{ $field->name }}">
        @foreach ($field->options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($selectDescription)
        <p class="inlay-field__option-description">{{ $selectDescription }}</p>
    @endif
    @include('inlay::fields.partials.footer', ['field' => $field, 'modelPath' => $modelPath, 'editingProps' => $editingProps])
</div>
