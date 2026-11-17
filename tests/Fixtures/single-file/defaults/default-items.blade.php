<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;

section(
    Section::make('default-items')
        ->settings(
            Settings::make()
                ->repeater('items', Settings::make()
                    ->text('title')->label('Title')
                    ->link('link')->label('Link')
                    ->image('photo')->label('Photo'))
                ->default([['title' => 'From default', 'link' => ['label' => 'More', 'url' => '/more']]])
        )
);
?>
@foreach ($items as $item)
<p>{{ $item['title'] }}|{{ $item['link']->url }}|{{ $item['photo'] === null ? 'no-photo' : 'photo' }}</p>
@endforeach
