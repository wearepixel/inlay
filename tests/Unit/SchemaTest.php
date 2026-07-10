<?php

declare(strict_types=1);

use WearePixel\BladePageBuilder\Schema\FieldType;
use WearePixel\BladePageBuilder\Schema\Schema;

it('builds a schema of fields including a class-mapped select', function () {
    $schema = Schema::make()
        ->text('heading', label: 'Heading')
        ->richtext('body', label: 'Body copy')
        ->image('photo', label: 'Photo')
        ->select('layout', label: 'Layout', options: [
            'image-left' => 'Image on left',
            'image-right' => 'Image on right',
        ])->mapsToClass(fn (string $value) => $value === 'image-left' ? 'flex-row-reverse' : 'flex-row');

    expect($schema->fields())->toHaveCount(4);

    $heading = $schema->field('heading');
    expect($heading->name)->toBe('heading')
        ->and($heading->type)->toBe(FieldType::Text)
        ->and($heading->label)->toBe('Heading');

    $layout = $schema->field('layout');
    expect($layout->type)->toBe(FieldType::Select)
        ->and($layout->options['options'])->toBe([
            'image-left' => 'Image on left',
            'image-right' => 'Image on right',
        ])
        ->and($layout->resolveClass('image-left'))->toBe('flex-row-reverse')
        ->and($layout->resolveClass('image-right'))->toBe('flex-row');

    expect($schema->field('missing'))->toBeNull();
});

it('nests a schema inside a repeater field', function () {
    $itemSchema = Schema::make()->text('question', label: 'Question')->textarea('answer', label: 'Answer');

    $schema = Schema::make()->repeater('faqs', label: 'FAQs', itemSchema: $itemSchema);

    $faqs = $schema->field('faqs');

    expect($faqs->type)->toBe(FieldType::Repeater)
        ->and($faqs->itemSchema)->toBe($itemSchema)
        ->and($faqs->itemSchema->fields())->toHaveCount(2);
});
