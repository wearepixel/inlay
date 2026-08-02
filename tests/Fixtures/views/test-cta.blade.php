<div>
    <a href="{{ $cta?->url ?? '' }}" @if ($cta?->openInNewTab) target="_blank" @endif>{{ $cta?->label ?? '' }}</a>
</div>
