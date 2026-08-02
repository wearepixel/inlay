<?php

declare(strict_types=1);

use WearePixel\Inlay\Attributes\Text;
use WearePixel\Inlay\Attributes\Toggle;
use WearePixel\Inlay\Contracts\InlayComponent;
use WearePixel\Inlay\Schema\FieldType;
use WearePixel\Inlay\Schema\Settings;
use WearePixel\Inlay\Support\ComponentDefinitionResolver;

it('infers PHP type, nullability, and default from the constructor for a fluent-only component', function () {
    $definition = (new ComponentDefinitionResolver)->resolve(ResolverFluentOnlyComponent::class);

    $heading = $definition->field('heading');

    expect($heading->nullable)->toBeFalse()
        ->and($heading->hasConstructorDefault)->toBeTrue()
        ->and($heading->default)->toBe('Welcome');
});

it('resolves settings for an attribute-only component with no settings() method at all', function () {
    $definition = (new ComponentDefinitionResolver)->resolve(ResolverAttributeOnlyComponent::class);

    expect($definition->fields())->toHaveCount(2);
    expect($definition->field('heading')->type)->toBe(FieldType::Text);
    expect($definition->field('showAction')->type)->toBe(FieldType::Toggle);
});

it('merges attribute and fluent declarations by name, with the fluent declaration winning for shared metadata', function () {
    $definition = (new ComponentDefinitionResolver)->resolve(ResolverMergedComponent::class);

    // "heading" is declared by both - the fluent label wins.
    expect($definition->field('heading')->label)->toBe('Fluent label wins');
    // "showAction" is attribute-only and survives the merge.
    expect($definition->field('showAction'))->not->toBeNull();
});

it('never exposes an unexposed constructor parameter as a setting', function () {
    $definition = (new ComponentDefinitionResolver)->resolve(ResolverWithServiceComponent::class);

    expect($definition->field('repository'))->toBeNull();
    expect($definition->fields())->toHaveCount(1);
});

it('rejects a declared setting with no matching constructor parameter', function () {
    (new ComponentDefinitionResolver)->resolve(ResolverMismatchedNameComponent::class);
})->throws(InvalidArgumentException::class, 'no matching constructor parameter');

it('rejects a required, non-nullable top-level setting with no constructor default', function () {
    (new ComponentDefinitionResolver)->resolve(ResolverNoDefaultComponent::class);
})->throws(InvalidArgumentException::class, 'has no constructor default and is not nullable');

it('rejects a declared enum that does not match the constructor parameter type', function () {
    (new ComponentDefinitionResolver)->resolve(ResolverEnumMismatchComponent::class);
})->throws(InvalidArgumentException::class, 'the constructor expects');

final class ResolverFluentOnlyComponent implements InlayComponent
{
    public function __construct(public string $heading = 'Welcome') {}

    public static function settings(): Settings
    {
        return Settings::make()->text('heading')->label('Heading');
    }
}

final class ResolverAttributeOnlyComponent implements InlayComponent
{
    public function __construct(
        #[Text(label: 'Heading')]
        public string $heading = 'Welcome',
        #[Toggle(label: 'Show action')]
        public bool $showAction = false,
    ) {}
}

final class ResolverMergedComponent implements InlayComponent
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

final class ResolverWithServiceComponent implements InlayComponent
{
    public function __construct(
        private ResolverServiceStub $repository,
        #[Text(label: 'Heading')]
        public string $heading = 'Welcome',
    ) {}
}

final class ResolverMismatchedNameComponent implements InlayComponent
{
    public function __construct(public string $heading = 'Welcome') {}

    public static function settings(): Settings
    {
        return Settings::make()->text('subtitle')->label('Subtitle');
    }
}

final class ResolverNoDefaultComponent implements InlayComponent
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

final class ResolverEnumMismatchComponent implements InlayComponent
{
    public function __construct(public ResolverImageSize $imageSize = ResolverImageSize::Large) {}

    public static function settings(): Settings
    {
        return Settings::make()->select('imageSize')->options(ResolverOtherEnum::class);
    }
}
