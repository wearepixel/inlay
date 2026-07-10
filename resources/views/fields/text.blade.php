<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <input type="text" wire:model.live.debounce.400ms="{{ $modelPath }}"
        class="w-full rounded-md border-gray-300 shadow-sm">
</div>
