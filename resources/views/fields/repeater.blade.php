@php($repeaterPath = \Illuminate\Support\Str::after($modelPath, 'editingProps.'))
<div class="inlay-repeater">
    @include('inlay::fields.partials.label', ['field' => $field])

    @foreach ((data_get($editingProps, $repeaterPath) ?? []) as $index => $item)
        <div class="inlay-repeater__item" wire:key="repeater-{{ $repeaterPath }}-{{ $index }}">
            @foreach ($field->itemSchema->fields() as $subField)
                @continue(! $subField->isVisible($item))
                @include('inlay::fields.' . $subField->type->value, [
                    'field' => $subField,
                    'modelPath' => "{$modelPath}.{$index}.{$subField->name}",
                ])
            @endforeach
            <button type="button" wire:click="removeRepeaterItem(@js($repeaterPath), {{ $index }})" class="inlay-button inlay-button--small inlay-button--danger">Remove</button>
        </div>
    @endforeach

    <button type="button" wire:click="addRepeaterItem(@js($repeaterPath))" class="inlay-button inlay-button--small">+ Add item</button>

    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
