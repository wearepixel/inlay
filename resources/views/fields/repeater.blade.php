<div class="inlay-repeater">
    <p class="inlay-field__label" style="margin-bottom: 10px;">{{ $field->label }}</p>

    @foreach (($editingProps[$field->name] ?? []) as $index => $item)
        <div class="inlay-repeater__item" wire:key="repeater-{{ $field->name }}-{{ $index }}">
            @foreach ($field->itemSchema->fields() as $subField)
                @continue(! $subField->isVisible($item))
                @include('inlay::fields.' . $subField->type->value, [
                    'field' => $subField,
                    'modelPath' => "editingProps.{$field->name}.{$index}.{$subField->name}",
                ])
            @endforeach
            <button type="button" wire:click="removeRepeaterItem(@js($field->name), {{ $index }})" class="inlay-button inlay-button--small inlay-button--danger">Remove</button>
        </div>
    @endforeach

    <button type="button" wire:click="addRepeaterItem(@js($field->name))" class="inlay-button inlay-button--small">+ Add item</button>

    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
