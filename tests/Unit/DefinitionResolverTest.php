<?php

declare(strict_types=1);

use WeArePixel\Inlay\Attributes\Text;
use WeArePixel\Inlay\Attributes\Toggle;
use WeArePixel\Inlay\Contracts\InlaySection;
use WeArePixel\Inlay\Schema\FieldType;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\DefinitionResolver;

it('infers PHP type, nullability, and default from the constructor for a fluent-only section', function () {
    $definition = (new DefinitionResolver)->resolve(ResolverFluentOnlySection::class);

    $heading = $definition->field('heading');

    expect($heading->nullable)->toBeFalse()
        ->and($heading->hasConstructorDefault)->toBeTrue()
        ->and($heading->default)->toBe('Welcome');
});

it('resolves settings for an attribute-only section with no settings() method at all', function () {
    $definition = (new DefinitionResolver)->resolve(ResolverAttributeOnlySection::class);

    expect($definition->fields())->toHaveCount(2);
    expect($definition->field('heading')->type)->toBe(FieldType::Text);
    expect($definition->field('showAction')->type)->toBe(FieldType::Toggle);
});

it('merges attribute and fluent declarations by name, with the fluent declaration winning for shared metadata', function () {
    $definition = (new DefinitionResolver)->resolve(ResolverMergedSection::class);

    // "heading" is declared by both - the fluent label wins.
    expect($definition->field('heading')->label)->toBe('Fluent label wins');
    // "showAction" is attribute-only and survives the merge.
    expect($definition->field('showAction'))->not->toBeNull();
});

it('never exposes an unexposed constructor parameter as a setting', function () {
    $definition = (new DefinitionResolver)->resolve(ResolverWithServiceSection::class);

    expect($definition->field('repository'))->toBeNull();
    expect($definition->fields())->toHaveCount(1);
});

it('rejects a declared setting with no matching constructor parameter', function () {
    (new DefinitionResolver)->resolve(ResolverMismatchedNameSection::class);
})->throws(InvalidArgumentException::class, 'no matching constructor parameter');

it('rejects a required, non-nullable top-level setting with no constructor default', function () {
    (new DefinitionResolver)->resolve(ResolverNoDefaultSection::class);
})->throws(InvalidArgumentException::class, 'has no constructor default and is not nullable');

it('rejects a declared enum that does not match the constructor parameter type', function () {
    (new DefinitionResolver)->resolve(ResolverEnumMismatchSection::class);
})->throws(InvalidArgumentException::class, 'the constructor expects');

final class ResolverFluentOnlySection implements InlaySection
{
    public function __construct(public string $heading = 'Welcome') {}

    public static function settings(): Settings
    {
        return Settings::make()->text('heading')->label('Heading');
    }
}

final class ResolverAttributeOnlySection implements InlaySection
{
    public function __construct(
        #[Text(label: 'Heading')]
        public string $heading = 'Welcome',
        #[Toggle(label: 'Show action')]
        public bool $showAction = false,
    ) {}
}

final class ResolverMergedSection implements InlaySection
{
    public function __construct(
        #[Text(label: 'Attribute label')]
        public string $heading = 'Welcome',
        #[Toggle(label: 'Show action')]
        public bool $showAction = false,
    ) {}

    public static function settings(): Settings
    {
        return Settings::make()->text('heading')->label('Fluent label wins');
    }
}

final class ResolverServiceStub
{
    //
}

final class ResolverWithServiceSection implements InlaySection
{
    public function __construct(
        private ResolverServiceStub $repository,
        #[Text(label: 'Heading')]
        public string $heading = 'Welcome',
    ) {}
}

final class ResolverMismatchedNameSection implements InlaySection
{
    public function __construct(public string $heading = 'Welcome') {}

    public static function settings(): Settings
    {
        return Settings::make()->text('subtitle')->label('Subtitle');
    }
}

final class ResolverNoDefaultSection implements InlaySection
{
    public function __construct(public string $heading) {}

    public static function settings(): Settings
    {
        return Settings::make()->text('heading')->label('Heading');
    }
}

enum ResolverImageSize: string
{
    case Small = 'small';
    case Large = 'large';
}

enum ResolverOtherEnum: string
{
    case One = 'one';
}

final class ResolverEnumMismatchSection implements InlaySection
{
    public function __construct(public ResolverImageSize $imageSize = ResolverImageSize::Large) {}

    public static function settings(): Settings
    {
        return Settings::make()->select('imageSize')->options(ResolverOtherEnum::class);
    }
}
