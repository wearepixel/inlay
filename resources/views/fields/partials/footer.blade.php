{{-- Expects $field (Setting), $modelPath, $editingProps --}}
@php
    $footerValue = data_get(['editingProps' => $editingProps], $modelPath);
    $footerIsEmpty = $footerValue === null || $footerValue === '' || $footerValue === [];
@endphp
@if ($field->required && $footerIsEmpty)
    <p class="inlay-field__hint">{{ __('inlay::admin.fields.required') }}</p>
@endif
@if ($field->help)
    <p class="inlay-field__help">{{ $field->help }}</p>
@endif
