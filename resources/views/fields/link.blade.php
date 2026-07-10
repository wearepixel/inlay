<div class="mb-4 space-y-2">
    <label class="block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    <input type="text" placeholder="Label" wire:model.live.debounce.400ms="{{ $modelPath }}.label"
        class="w-full rounded-md border-gray-300 shadow-sm">
    <input type="url" placeholder="URL" wire:model.live.debounce.400ms="{{ $modelPath }}.url"
        class="w-full rounded-md border-gray-300 shadow-sm">
    <label class="flex items-center gap-2 text-sm text-gray-600">
        <input type="checkbox" wire:model.live="{{ $modelPath }}.new_tab">
        Open in new tab
    </label>
</div>
