<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <textarea wire:model.live.debounce.400ms="{{ $modelPath }}" rows="8"
        class="w-full rounded-md border-gray-300 shadow-sm"></textarea>
</div>
