<div class="mb-4 space-y-3 rounded-md border border-gray-200 p-3">
    <p class="text-sm font-medium text-gray-700">{{ $field->label }}</p>

    @foreach (($editingProps[$field->name] ?? []) as $index => $item)
        <div class="space-y-2 rounded-md border border-gray-100 p-2" wire:key="repeater-{{ $field->name }}-{{ $index }}">
            @foreach ($field->itemSchema->fields() as $subField)
                @include('inlay::fields.' . $subField->type->value, [
                    'field' => $subField,
                    'modelPath' => "editingProps.{$field->name}.{$index}.{$subField->name}",
                ])
            @endforeach
            <button type="button" wire:click="removeRepeaterItem('{{ $field->name }}', {{ $index }})"
                class="text-sm text-red-600">Remove</button>
        </div>
    @endforeach

    <button type="button" wire:click="addRepeaterItem('{{ $field->name }}')"
        class="text-sm text-blue-600">Add item</button>
</div>
