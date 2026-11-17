<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('stack')
        ->name('Stack')
        ->settings(
            Settings::make()
                ->text('eyebrow')->label('Eyebrow')->default('')
                ->blocks('content')->label('Content')->allow(['heading', 'text'])->max(3)->default([
                    ['type' => 'heading', 'settings' => ['text' => 'Default heading']],
                ])
        )
);
?>
<section class="stack">{{ $content }}</section>
