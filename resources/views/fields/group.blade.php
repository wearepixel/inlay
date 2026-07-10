<div class="mb-4 space-y-3 rounded-md border border-gray-200 p-3">
    <p class="text-sm font-medium text-gray-700">{{ $field->label }}</p>

    @foreach ($field->itemSchema->fields() as $subField)
        @include('blade-page-builder::fields.' . $subField->type->value, [
            'field' => $subField,
            'modelPath' => "editingProps.{$field->name}.{$subField->name}",
        ])
    @endforeach
</div>
