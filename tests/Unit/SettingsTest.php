<?php

declare(strict_types=1);

use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\DescribesSettingOptions;
use WeArePixel\Inlay\Support\SettingOptions;

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

it('groups fields declared inside section() under a named, collapsible heading', function () {
    $settings = Settings::make()
        ->text('heading')->label('Heading')
        ->section('Appearance', function (Settings $settings) {
            $settings->select('alignment')->label('Alignment');
            $settings->number('imageSize')->label('Image size');
        });

    expect($settings->fields())->toHaveCount(3)
        ->and($settings->field('heading')->group)->toBeNull()
        ->and($settings->field('alignment')->group)->toBe('Appearance')
        ->and($settings->field('imageSize')->group)->toBe('Appearance');
});

it('groups fields declared inside tab() under a named tab', function () {
    $settings = Settings::make()
        ->tab('Content', function (Settings $settings) {
            $settings->text('heading')->label('Heading');
        })
        ->tab('SEO', function (Settings $settings) {
            $settings->text('metaTitle')->label('Meta title');
        });

    expect($settings->field('heading')->tab)->toBe('Content')
        ->and($settings->field('metaTitle')->tab)->toBe('SEO');
});

it('composes tab() and section() - a section declared inside a tab keeps both labels', function () {
    $settings = Settings::make()->tab('Content', function (Settings $settings) {
        $settings->section('Hero', function (Settings $settings) {
            $settings->text('heading')->label('Heading');
        });
    });

    $heading = $settings->field('heading');

    expect($heading->tab)->toBe('Content')
        ->and($heading->group)->toBe('Hero');
});

it('rejects a duplicate setting name declared inside section()/tab() the same way as a top-level duplicate', function () {
    Settings::make()->text('heading')->section('Content', function (Settings $settings) {
        $settings->text('heading');
    });
})->throws(InvalidArgumentException::class, 'Duplicate setting [heading]');

it('attaches an optional presentational badge to a field', function () {
    $settings = Settings::make()->text('heading')->label('Heading')->badge('New');

    expect($settings->field('heading')->badge)->toBe('New');
});

it('exposes an enum case\'s optional description via DescribesSettingOptions', function () {
    $settings = Settings::make()->select('imageSize')->options(SettingsTestImageSizeWithDescriptions::class);

    $case = SettingsTestImageSizeWithDescriptions::Large;

    expect($case)->toBeInstanceOf(DescribesSettingOptions::class)
        ->and($case->optionDescription())->toBe('Full-width, best for a strong first impression.')
        ->and($settings->field('imageSize')->options)->toBe([
            'small' => 'Small',
            'large' => 'Large',
        ]);
});

enum SettingsTestImageSizeWithDescriptions: string implements DescribesSettingOptions, SettingOptions
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

    public function optionDescription(): ?string
    {
        return match ($this) {
            self::Small => null,
            self::Large => 'Full-width, best for a strong first impression.',
        };
    }
}

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
