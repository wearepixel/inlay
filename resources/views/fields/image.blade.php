<div class="mb-4">
    <label class="mb-1 block text-sm font-medium text-gray-700">{{ $field->label }}</label>
    @if (! empty($editingProps[$field->name]))
        <img src="{{ Illuminate\Support\Facades\Storage::disk(config('inlay.disk', 'public'))->url($editingProps[$field->name]) }}"
            class="mb-2 h-24 w-auto rounded object-cover">
    @endif
    <input type="file" wire:model="imageUploads.{{ $field->name }}" accept="image/*">
    @error('imageUploads.' . $field->name)
        <span class="text-sm text-red-600">{{ $message }}</span>
    @enderror
</div>
