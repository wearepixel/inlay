@include('inlay::fields.partials.label', ['field' => $field])
@php
    $imageFieldId = 'image-field-' . \Illuminate\Support\Str::slug($modelPath);
    $imagePath = \Illuminate\Support\Str::after($modelPath, 'editingProps.');
    $imageValue = data_get($editingProps, $imagePath);
@endphp
<div class="inlay-field">
    @if (is_string($imageValue) && $imageValue !== '')
        <div class="inlay-image-field__preview">
            <img
                src="{{ (new \WeArePixel\Inlay\Support\ImageValue($imageValue))->url() }}"
                alt=""
                class="inlay-image-field__thumb"
            >
            <input id="{{ $imageFieldId }}" type="file" wire:model="imageUploads.{{ $imagePath }}" accept="image/*" data-test="setting-{{ $field->name }}" class="inlay-sr-only inlay-image-field__input">
            <label for="{{ $imageFieldId }}" class="inlay-button inlay-button--small inlay-image-field__replace">
                <span wire:loading.remove wire:target="imageUploads.{{ $imagePath }}">Replace image</span>
                <span wire:loading wire:target="imageUploads.{{ $imagePath }}">Uploading...</span>
            </label>
        </div>
    @else
        <input id="{{ $imageFieldId }}" type="file" wire:model="imageUploads.{{ $imagePath }}" accept="image/*" data-test="setting-{{ $field->name }}" class="inlay-sr-only inlay-image-field__input">
        <label for="{{ $imageFieldId }}" class="inlay-image-field__dropzone">
            <x-inlay::icon name="image" :size="20" />
            <span wire:loading.remove wire:target="imageUploads.{{ $imagePath }}">Click to upload an image</span>
            <span wire:loading wire:target="imageUploads.{{ $imagePath }}">Uploading...</span>
        </label>
    @endif
    @error('imageUploads.' . $imagePath)
        <p class="inlay-field__warning">{{ $message }}</p>
    @enderror
    @include('inlay::fields.partials.footer', ['field' => $field, 'modelPath' => $modelPath, 'editingProps' => $editingProps])
</div>
