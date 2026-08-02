<div class="inlay-group">
    <p class="inlay-field__label" style="margin-bottom: 10px;">{{ $field->label }}</p>

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
