<div>
    <ul>
        @foreach ($sections as $section)
            <li wire:key="section-{{ $section->id }}" wire:click="selectSection({{ $section->id }})">
                {{ $section->section_type }}
            </li>
        @endforeach
    </ul>

    @if ($selectedSchema)
        <div>
            @foreach ($selectedSchema->fields() as $field)
                @include('blade-page-builder::fields.' . $field->type->value, [
                    'field' => $field,
                    'modelPath' => "editingProps.{$field->name}",
                ])
            @endforeach
        </div>
    @endif
</div>
