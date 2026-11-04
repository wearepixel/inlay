<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('rich-text')
        ->name('Rich text')
        ->category('Content')
        ->description('A single column of formatted text: paragraphs, subheadings (h2-h4), lists, links and quotes. Use for terms, longer explanations or anything no other block fits.')
        ->keywords(['text', 'content', 'article', 'paragraphs', 'copy'])
        ->settings(
            Settings::make()
                ->richtext('body')->label('Content')->required()->default('<h2>Section heading</h2><p>Write your content here.</p>')
                ->select('width')->label('Width')->options(['narrow' => 'Narrow (best for reading)', 'wide' => 'Wide'])->default('narrow')
        )
);
?>
<section class="inlay-section inlay-rich-text">
    <div class="inlay-container {{ $width === 'wide' ? 'inlay-container--wide-text' : 'inlay-container--narrow' }} inlay-prose">
        {!! app(\WeArePixel\Inlay\Support\RichTextSanitizer::class)->sanitize($body) !!}
    </div>
</section>
