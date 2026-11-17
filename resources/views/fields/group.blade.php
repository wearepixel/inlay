<div class="inlay-group">
    @include('inlay::fields.partials.label', ['field' => $field])

    @foreach ($field->itemSchema->fields() as $subField)
        @continue(! $subField->isVisible(data_get($editingProps, \Illuminate\Support\Str::after($modelPath, 'editingProps.')) ?? []))
        @include('inlay::fields.' . $subField->type->value, [
            'field' => $subField,
            'modelPath' => "{$modelPath}.{$subField->name}",
        ])
    @endforeach

    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
