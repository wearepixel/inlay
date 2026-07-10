<div class="grid grid-cols-3 gap-6">
    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    @endonce

    <div class="col-span-1 space-y-4">
        <div
            x-data
            x-init="
                Sortable.create($refs.sectionList, {
                    animation: 150,
                    handle: '.drag-handle',
                    onEnd: () => {
                        const orderedIds = Array.from($refs.sectionList.children).map(el => el.dataset.sectionId);
                        $wire.updateOrder(orderedIds);
                    },
                });
            "
        >
            <ul x-ref="sectionList" class="space-y-2">
                @foreach ($sections as $section)
                    <li
                        data-section-id="{{ $section->id }}"
                        wire:key="section-{{ $section->id }}"
                        wire:click="selectSection({{ $section->id }})"
                        class="flex items-center gap-2 rounded-md border border-gray-200 p-3 {{ $selectedSectionId === $section->id ? 'ring-2 ring-blue-500' : '' }}"
                    >
                        <span class="drag-handle cursor-move text-gray-400">::</span>
                        <span>{{ $section->section_type }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="space-y-1">
            <p class="text-sm font-medium text-gray-700">Add section</p>
            @foreach ($availableSections as $slug)
                <button type="button" wire:click="addSection('{{ $slug }}')"
                    class="block w-full rounded-md border border-gray-200 p-2 text-left text-sm hover:bg-gray-50">
                    {{ $slug }}
                </button>
            @endforeach
        </div>

        <button type="button" wire:click="publish"
            class="w-full rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-500">
            Publish
        </button>
    </div>

    <div class="col-span-1">
        @if ($selectedSchema)
            @foreach ($selectedSchema->fields() as $field)
                @include('blade-page-builder::fields.' . $field->type->value, [
                    'field' => $field,
                    'modelPath' => "editingProps.{$field->name}",
                ])
            @endforeach
        @endif
    </div>

    <div class="col-span-1 rounded-md border border-gray-200 p-4">
        {{ $previewHtml }}
    </div>
</div>
