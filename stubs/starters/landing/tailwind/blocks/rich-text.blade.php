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
<section class="bg-white py-16">
    <div class="mx-auto px-6 {{ $width === 'wide' ? 'max-w-5xl' : 'max-w-3xl' }} space-y-5 text-lg leading-8 text-gray-700 [&_a]:text-indigo-600 [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-gray-200 [&_blockquote]:pl-4 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:text-gray-900 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-gray-900 [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:list-disc [&_ul]:pl-6">
        {!! app(\WeArePixel\Inlay\Support\RichTextSanitizer::class)->sanitize($body) !!}
    </div>
</section>
