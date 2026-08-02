<div class="inlay-group">
    @include('inlay::fields.partials.label', ['field' => $field])

    @foreach ($field->itemSchema->fields() as $subField)
        @continue(! $subField->isVisible($editingProps[$field->name] ?? []))
        @include('inlay::fields.' . $subField->type->value, [
            'field' => $subField,
            'modelPath' => "editingProps.{$field->name}.{$subField->name}",
        ])
    @endforeach

    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
