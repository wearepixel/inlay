@include('inlay::fields.partials.label', ['field' => $field])
<div class="inlay-field">
    @if (! empty($editingProps[$field->name]))
        <img
            src="{{ Illuminate\Support\Facades\Storage::disk(config('inlay.disk', 'public'))->url($editingProps[$field->name]) }}"
            style="display: block; height: 96px; width: auto; border-radius: var(--inlay-radius); margin-bottom: 8px; object-fit: cover;"
        >
    @endif
    <input type="file" wire:model="imageUploads.{{ $field->name }}" accept="image/*" data-test="setting-{{ $field->name }}">
    @error('imageUploads.' . $field->name)
        <p class="inlay-field__warning">{{ $message }}</p>
    @enderror
    @include('inlay::fields.partials.footer', ['field' => $field, 'modelPath' => $modelPath, 'editingProps' => $editingProps])
</div>
