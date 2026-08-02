<div class="{{ $layoutClass() }}">
    <h1>{{ $heading }}</h1>
    @if ($photo)
        <img src="{{ $photo }}" data-test="photo">
    @endif
</div>
