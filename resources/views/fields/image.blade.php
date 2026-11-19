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
                <span wire:loading.remove wire:target="imageUploads.{{ $imagePath }}">{{ __('inlay::admin.fields.replace_image') }}</span>
                <span wire:loading wire:target="imageUploads.{{ $imagePath }}">{{ __('inlay::admin.fields.uploading') }}</span>
            </label>
            @unless ($field->required)
                <button type="button" wire:click="clearField('{{ $imagePath }}')" class="inlay-button inlay-button--small inlay-button--ghost inlay-button--danger" data-test="remove-{{ $field->name }}">{{ __('inlay::admin.common.remove') }}</button>
            @endunless
        </div>
    @else
        <input id="{{ $imageFieldId }}" type="file" wire:model="imageUploads.{{ $imagePath }}" accept="image/*" data-test="setting-{{ $field->name }}" class="inlay-sr-only inlay-image-field__input">
        <label for="{{ $imageFieldId }}" class="inlay-image-field__dropzone">
            <x-inlay::icon name="image" :size="20" />
            <span wire:loading.remove wire:target="imageUploads.{{ $imagePath }}">{{ __('inlay::admin.fields.upload_image') }}</span>
            <span wire:loading wire:target="imageUploads.{{ $imagePath }}">{{ __('inlay::admin.fields.uploading') }}</span>
        </label>
    @endif
    @error('imageUploads.' . $imagePath)
        <p class="inlay-field__warning">{{ $message }}</p>
    @enderror
    @include('inlay::fields.partials.footer', ['field' => $field, 'modelPath' => $modelPath, 'editingProps' => $editingProps])
</div>
