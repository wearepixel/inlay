<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Support;

use BackedEnum;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionNamedType;
use WeArePixel\Inlay\Attributes\FieldAttribute;
use WeArePixel\Inlay\Schema\Setting;
use WeArePixel\Inlay\Schema\Settings;

/**
 * Builds a section's resolved Settings definition from constructor-property
 * attributes, the optional fluent settings() method, and the constructor
 * itself - the exclusive source of truth for a top-level setting's
 * nullability and default value. See docs/v1-implementation-specification.md
 * §7-§9.
 *
 * A constructor parameter is never an editor setting unless explicitly
 * exposed by an attribute or a settings() entry - anything else (an injected
 * service, say) is left entirely alone and resolved by the container as
 * usual when the section is rendered.
 */
final class DefinitionResolver
{
    /**
     * @param  class-string  $sectionClass
     */
    public function resolve(string $sectionClass): Settings
    {
        $reflection = new ReflectionClass($sectionClass);
        $parameters = $reflection->getConstructor()?->getParameters() ?? [];

        $paramsByName = [];
        foreach ($parameters as $parameter) {
            $paramsByName[$parameter->getName()] = $parameter;
        }

        $merged = $this->mergeAttributesAndFluentSettings($sectionClass, $parameters);

        foreach ($merged->fields() as $setting) {
            $this->applyConstructorMetadata($sectionClass, $setting, $paramsByName);
        }

        return $merged;
    }

    /**
     * @param  array<int, \ReflectionParameter>  $parameters
     */
    private function mergeAttributesAndFluentSettings(string $sectionClass, array $parameters): Settings
    {
        /** @var array<string, Setting> $attributeSettings */
        $attributeSettings = [];

        foreach ($parameters as $parameter) {
            foreach ($parameter->getAttributes(FieldAttribute::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                /** @var FieldAttribute $instance */
                $instance = $attribute->newInstance();
                $attributeSettings[$parameter->getName()] = $instance->toSetting($parameter->getName());
            }
        }

        $fluentSettings = method_exists($sectionClass, 'settings')
            ? $sectionClass::settings()
            : Settings::make();

        $merged = Settings::make();
        $declaredNames = [];

        // Fluent settings() declarations take precedence for shared metadata -
        // adopted first, so an attribute for the same name is simply skipped
        // below rather than needing an explicit override step.
        foreach ($fluentSettings->fields() as $field) {
            $merged->adopt($field);
            $declaredNames[$field->name] = true;
        }

        foreach ($attributeSettings as $name => $field) {
            if (! isset($declaredNames[$name])) {
                $merged->adopt($field);
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, \ReflectionParameter>  $paramsByName
     */
    private function applyConstructorMetadata(string $sectionClass, Setting $setting, array $paramsByName): void
    {
        if (! isset($paramsByName[$setting->name])) {
            throw new InvalidArgumentException(
                "Section [{$sectionClass}] declares a setting [{$setting->name}] with no matching constructor parameter."
            );
        }

        $parameter = $paramsByName[$setting->name];
        $type = $parameter->getType();

        $setting->nullable = ! $type || ($type instanceof ReflectionNamedType && $type->allowsNull());
        $setting->hasConstructorDefault = $parameter->isDefaultValueAvailable();
        $setting->default = $setting->hasConstructorDefault ? $parameter->getDefaultValue() : null;

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_a($type->getName(), BackedEnum::class, true)) {
            $setting->enumClass = $type->getName();

            if ($setting->declaredEnumClass !== null && $setting->declaredEnumClass !== $setting->enumClass) {
                throw new InvalidArgumentException(
                    "Section [{$sectionClass}] setting [{$setting->name}] declares options for [{$setting->declaredEnumClass}], but the constructor expects [{$setting->enumClass}]."
                );
            }

            if ($setting->options === []) {
                $setting->options = Settings::labelsFromEnum($setting->enumClass);
            }
        }

        if (! $setting->hasConstructorDefault && ! $setting->nullable) {
            throw new InvalidArgumentException(
                "Section [{$sectionClass}] setting [{$setting->name}] has no constructor default and is not nullable. ".
                'A top-level setting must always be constructible with zero overrides - give it a default or make it nullable. '.
                'A schema-level default is not permitted to substitute for one here.'
            );
        }
    }
}
