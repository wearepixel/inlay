<div>
    <ul>
        @foreach ($sections as $section)
            <li wire:key="section-{{ $section->id }}" wire:click="selectSection({{ $section->id }})">
                {{ $section->section_type }}
            </li>
        @endforeach
    </ul>
</div>
