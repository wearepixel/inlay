<?php

use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\SingleFile\Section;
use WeArePixel\Inlay\Support\LinkValue;

section(
    Section::make('promo')
        ->name('Promo')
        ->category('Marketing')
        ->description('A promotional band with every field type.')
        ->settings(
            Settings::make()
                ->text('heading')->label('Heading')->required()->maxLength(20)->default('Fresh meals, delivered')
                ->richtext('body')->label('Body')->default('')
                ->number('columns')->label('Columns')->default(3)
                ->toggle('dark')->label('Dark')->default(false)
                ->select('align')->label('Align')->options(['left' => 'Left', 'center' => 'Center'])->default('left')
                ->image('photo')->label('Photo')->default('')
                ->link('cta')->label('Call to action')->default(new LinkValue)
                ->repeater('items', Settings::make()
                    ->text('title')->label('Title')->required()
                    ->link('link')->label('Link')
                    ->richtext('detail')->label('Detail'))->label('Items')->default([])
                ->group('badge', Settings::make()
                    ->text('text')->label('Text')
                    ->toggle('visible')->label('Visible'))->label('Badge')->default([])
        )
);
?>
<section data-test="promo" class="{{ $dark ? 'dark' : '' }}">
    <h2>{{ $heading }}</h2>
    @if ($cta->url !== '')<a href="{{ $cta->url }}">{{ $cta->label }}</a>@endif
    @foreach ($items as $item)<p>{{ $item['title'] }}</p>@endforeach
</section>
