@include('inlay::fields.partials.label', ['field' => $field])
<div class="inlay-field">
    <textarea wire:model.live.debounce.400ms="{{ $modelPath }}" rows="8" class="inlay-textarea" data-test="setting-{{ $field->name }}"></textarea>
    @include('inlay::fields.partials.footer', ['field' => $field, 'modelPath' => $modelPath, 'editingProps' => $editingProps])
</div>
