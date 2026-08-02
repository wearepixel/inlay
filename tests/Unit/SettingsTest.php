<?php

declare(strict_types=1);

use WearePixel\Inlay\Schema\FieldType;
use WearePixel\Inlay\Schema\Settings;
use WearePixel\Inlay\Support\SettingOptions;

it('builds a settings definition with chained modifiers per field', function () {
    $settings = Settings::make()
        ->text('heading')->label('Heading')->required()->maxLength(100)
        ->richtext('body')->label('Body copy')
        ->image('photo')->label('Photo')
        ->select('layout')->label('Layout')->options([
            'image-left' => 'Image on left',
            'image-right' => 'Image on right',
        ]);

    expect($settings->fields())->toHaveCount(4);

    $heading = $settings->field('heading');
    expect($heading->name)->toBe('heading')
        ->and($heading->type)->toBe(FieldType::Text)
        ->and($heading->label)->toBe('Heading')
        ->and($heading->required)->toBeTrue()
        ->and($heading->maxLength)->toBe(100);

    $layout = $settings->field('layout');
    expect($layout->type)->toBe(FieldType::Select)
        ->and($layout->options)->toBe([
            'image-left' => 'Image on left',
            'image-right' => 'Image on right',
        ]);

    expect($settings->field('missing'))->toBeNull();
});

it('derives select options from an enum, using SettingOptions labels where implemented', function () {
    $settings = Settings::make()->select('imageSize')->options(SettingsTestImageSize::class);

    expect($settings->field('imageSize')->options)->toBe([
        'small' => 'Small',
        'large' => 'Large',
    ]);
});

it('nests a settings definition inside a repeater field', function () {
    $itemSchema = Settings::make()->text('question')->label('Question')->textarea('answer')->label('Answer');

    $settings = Settings::make()->repeater('faqs', $itemSchema)->label('FAQs');

    $faqs = $settings->field('faqs');

    expect($faqs->type)->toBe(FieldType::Repeater)
        ->and($faqs->itemSchema)->toBe($itemSchema)
        ->and($faqs->itemSchema->fields())->toHaveCount(2);
});

it('hiddenWhen() is the literal inverse of visibleWhen(), not a second mechanism', function () {
    $settings = Settings::make()->toggle('showAction')->text('label')->hiddenWhen('showAction', false);

    $field = $settings->field('label');

    expect($field->isVisible(['showAction' => true]))->toBeTrue()
        ->and($field->isVisible(['showAction' => false]))->toBeFalse();
});

it('rejects a duplicate setting name declared twice in the same definition', function () {
    Settings::make()->text('heading')->text('heading');
})->throws(InvalidArgumentException::class, 'Duplicate setting [heading]');

it('requires a field definition before a modifier can be chained', function () {
    Settings::make()->label('Heading');
})->throws(LogicException::class, 'label() must follow a field definition.');

enum SettingsTestImageSize: string implements SettingOptions
{
    case Small = 'small';
    case Large = 'large';

    public function label(): string
    {
        return match ($this) {
            self::Small => 'Small',
            self::Large => 'Large',
        };
    }
}
