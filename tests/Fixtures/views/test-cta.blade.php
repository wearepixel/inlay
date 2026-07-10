<div>
    <a href="{{ $cta['url'] ?? '' }}" @if (! empty($cta['new_tab'])) target="_blank" @endif>{{ $cta['label'] ?? '' }}</a>
</div>
