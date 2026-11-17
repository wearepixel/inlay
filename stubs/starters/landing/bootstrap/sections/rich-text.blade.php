<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('rich-text')
        ->name('Rich text')
        ->category('Content')
        ->description('A single column of formatted text: paragraphs, subheadings (h2-h4), lists, links and quotes. Use for terms, longer explanations or anything no other section fits.')
        ->keywords(['text', 'content', 'article', 'paragraphs', 'copy'])
        ->settings(
            Settings::make()
                ->richtext('body')->label('Content')->required()->default('<h2>Section heading</h2><p>Write your content here.</p>')
                ->select('width')->label('Width')->options(['narrow' => 'Narrow (best for reading)', 'wide' => 'Wide'])->default('narrow')
        )
);
?>
<section class="bg-body py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="{{ $width === 'wide' ? 'col-lg-10' : 'col-lg-8' }} fs-5">
                {!! app(\WeArePixel\Inlay\Support\RichTextSanitizer::class)->sanitize($body) !!}
            </div>
        </div>
    </div>
</section>
