<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;
use WeArePixel\Inlay\Support\LinkValue;

block(
    Block::make('button')
        ->name('Button')
        ->description('A link styled as a button.')
        ->settings(
            Settings::make()
                ->link('link')->label('Link')->default(new LinkValue('Find out more', '#'))
                ->select('style')->label('Style')->options(['primary' => 'Primary', 'secondary' => 'Secondary'])->default('primary')
        )
);
?>
@if ($link->url !== '')
    <a
        href="{{ $link->url }}"
        @if ($link->openInNewTab) target="_blank" rel="noopener" @endif
        class="inlay-button{{ $style === 'secondary' ? ' inlay-button--outline' : '' }}"
    >{{ $link->label }}</a>
@endif
