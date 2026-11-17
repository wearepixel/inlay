<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;
use WeArePixel\Inlay\Support\LinkValue;

section(
    Section::make('cta-band')
        ->name('Call to action')
        ->category('Conversion')
        ->description('A full-width coloured band with a short heading, one line of text and up to two buttons. Use to close a page or break up long content - not at the very top (use Hero there).')
        ->keywords(['cta', 'banner', 'sign up', 'get started', 'closing'])
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->required()->maxLength(90)->default('Ready to get started?')
                ->textarea('body')->label('Text')->maxLength(200)->default('')
                ->link('primaryCta')->label('Primary button')->default(new LinkValue('Get started', '#'))
                ->link('secondaryCta')->label('Secondary button')->default(new LinkValue)
                ->select('tone')->label('Background')->options(['brand' => 'Brand colour', 'dark' => 'Dark', 'light' => 'Light'])->default('brand')
        )
);
?>
<section class="inlay-section inlay-tone-{{ $tone }} inlay-cta">
    <div class="inlay-container inlay-container--narrow inlay-center">
        <h2 class="inlay-heading">{{ $heading }}</h2>
        @if ($body !== '')
            <p class="inlay-lead">{{ $body }}</p>
        @endif
        <div class="inlay-actions inlay-actions--center">
            @if ($primaryCta->url !== '')
                <a href="{{ $primaryCta->url }}" @if ($primaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="inlay-button {{ $tone === 'light' ? '' : 'inlay-button--inverse' }}">{{ $primaryCta->label }}</a>
            @endif
            @if ($secondaryCta->url !== '')
                <a href="{{ $secondaryCta->url }}" @if ($secondaryCta->openInNewTab) target="_blank" rel="noopener" @endif class="inlay-button inlay-button--outline">{{ $secondaryCta->label }}</a>
            @endif
        </div>
    </div>
</section>
