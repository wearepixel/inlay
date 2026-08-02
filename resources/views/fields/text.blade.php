@include('inlay::fields.partials.label', ['field' => $field])
<div class="inlay-field">
    <input type="text" wire:model.live.debounce.400ms="{{ $modelPath }}" class="inlay-input" data-test="setting-{{ $field->name }}" @if ($field->maxLength) maxlength="{{ $field->maxLength }}" @endif>
    @include('inlay::fields.partials.footer', ['field' => $field, 'modelPath' => $modelPath, 'editingProps' => $editingProps])
</div>
