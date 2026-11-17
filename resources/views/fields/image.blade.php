@include('inlay::fields.partials.label', ['field' => $field])
@php $imageFieldId = 'image-field-' . \Illuminate\Support\Str::slug($modelPath); @endphp
<div class="inlay-field">
    @if (! empty($editingProps[$field->name]))
        <div class="inlay-image-field__preview">
            <img
                src="{{ (new \WeArePixel\Inlay\Support\ImageValue($editingProps[$field->name]))->url() }}"
                alt=""
                class="inlay-image-field__thumb"
            >
        </div>
        {{-- Kept as a plain, always-visible input here (unlike the empty
             state below) so replacing an existing image stays a real,
             directly-focusable control with its own native focus ring,
             rather than one hidden behind a styled label. --}}
        <input id="{{ $imageFieldId }}" type="file" wire:model="imageUploads.{{ $field->name }}" accept="image/*" data-test="setting-{{ $field->name }}">
    @else
        <input id="{{ $imageFieldId }}" type="file" wire:model="imageUploads.{{ $field->name }}" accept="image/*" data-test="setting-{{ $field->name }}" class="inlay-sr-only inlay-image-field__input">
        <label for="{{ $imageFieldId }}" class="inlay-image-field__dropzone">
            <x-inlay::icon name="image" :size="20" />
            <span>Click to upload an image</span>
        </label>
    @endif
    @error('imageUploads.' . $field->name)
        <p class="inlay-field__warning">{{ $message }}</p>
    @enderror
    @include('inlay::fields.partials.footer', ['field' => $field, 'modelPath' => $modelPath, 'editingProps' => $editingProps])
</div>
