{{-- Expects $field (Setting) --}}
<div class="inlay-field__label-row">
    <label class="inlay-field__label">{{ $field->label }}</label>
    @if ($field->required)
        <span class="inlay-required-indicator" aria-hidden="true">*</span>
    @endif
    @if ($field->badge)
        <span class="inlay-badge inlay-badge--field">{{ $field->badge }}</span>
    @endif
</div>
