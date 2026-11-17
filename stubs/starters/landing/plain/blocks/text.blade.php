<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Block;

block(
    Block::make('text')
        ->name('Text')
        ->description('Formatted paragraphs, lists and links.')
        ->settings(
            Settings::make()
                ->richtext('body')->label('Text')->required()->default('<p>Write something here.</p>')
        )
);
?>
<div class="inlay-text">
    {!! app(\WeArePixel\Inlay\Support\RichTextSanitizer::class)->sanitize($body) !!}
</div>
