<section data-testid="browser-hero">
    <h1>{{ $heading }}</h1>

    @if ($showCta)
        <a href="#" data-testid="browser-hero-cta">{{ $ctaLabel }}</a>
    @endif
</section>
