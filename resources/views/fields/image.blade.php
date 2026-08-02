<div class="inlay-field">
    <label class="inlay-field__label">{{ $field->label }}</label>
    @if (! empty($editingProps[$field->name]))
        <img
            src="{{ Illuminate\Support\Facades\Storage::disk(config('inlay.disk', 'public'))->url($editingProps[$field->name]) }}"
            style="display: block; height: 96px; width: auto; border-radius: var(--inlay-radius); margin-bottom: 8px; object-fit: cover;"
        >
    @endif
    <input type="file" wire:model="imageUploads.{{ $field->name }}" accept="image/*">
    @error('imageUploads.' . $field->name)
        <p class="inlay-field__help" style="color: var(--inlay-danger);">{{ $message }}</p>
    @enderror
    @if ($field->help)
        <p class="inlay-field__help">{{ $field->help }}</p>
    @endif
</div>
