<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\SiteSettings;

use Illuminate\Support\Str;
use InvalidArgumentException;
use WeArePixel\Inlay\Models\SiteSetting;
use WeArePixel\Inlay\Schema\Settings;
use WeArePixel\Inlay\Support\RichTextSanitizer;
use WeArePixel\Inlay\Support\SectionRenderer;
use WeArePixel\Inlay\Support\SettingsCoercer;

/**
 * Content that belongs to the whole site rather than one page - a phone
 * number in the footer, the header's navigation links. Declared with the
 * same Settings schema as a section, edited in the admin, and live as soon
 * as it is saved: there is no draft or publish step.
 */
final class SiteSettingsRegistry
{
    /** @var array<string, SiteSettingsGroup> */
    private array $groups = [];

    /** @var array<string, array<string, mixed>> */
    private array $resolved = [];

    public function register(string $handle, Settings $schema, ?string $label = null): void
    {
        if (! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $handle)) {
            throw new InvalidArgumentException("Site settings handle [{$handle}] must be lowercase letters, numbers, dashes or underscores.");
        }

        $this->groups[$handle] = new SiteSettingsGroup($handle, $label ?? Str::headline($handle), $schema);
        unset($this->resolved[$handle]);
    }

    /**
     * @return array<string, SiteSettingsGroup>
     */
    public function all(): array
    {
        return $this->groups;
    }

    public function group(string $handle): ?SiteSettingsGroup
    {
        return $this->groups[$handle] ?? null;
    }

    /**
     * Every field of a group, resolved over its defaults and hydrated the
     * same way a section's settings are (images to ImageValue, links to
     * LinkValue, selects to their enum).
     *
     * @return array<string, mixed>
     */
    public function values(string $handle): array
    {
        $group = $this->requireGroup($handle);

        return $this->resolved[$handle] ??= app(SectionRenderer::class)->resolveSchemaSettings(
            $group->schema,
            "site-settings:{$handle}",
            $this->storedWithDefaults($group),
        );
    }

    /**
     * The editor's form state: the same values as plain, serialisable data.
     *
     * @return array<string, mixed>
     */
    public function rawValues(string $handle): array
    {
        $group = $this->requireGroup($handle);

        return app(SectionRenderer::class)->resolveSchemaSettings(
            $group->schema,
            "site-settings:{$handle}",
            $this->storedWithDefaults($group),
            raw: true,
        );
    }

    /**
     * "contact.phone" reads the phone field of the contact group.
     */
    public function value(string $key, mixed $default = null): mixed
    {
        [$handle, $field] = array_pad(explode('.', $key, 2), 2, null);

        if ($field === null || ! isset($this->groups[$handle])) {
            return $default;
        }

        return data_get($this->values($handle), $field, $default);
    }

    /**
     * Stores one field, coerced to its declared type. A field the group
     * does not declare is ignored, so an editor cannot store arbitrary keys.
     *
     * @return mixed the stored value, or null when the field is not declared
     */
    public function store(string $handle, string $field, mixed $value): mixed
    {
        $setting = $this->requireGroup($handle)->schema->field($field);

        if ($setting === null) {
            return null;
        }

        $coerced = app(RichTextSanitizer::class)->sanitizeSetting($setting, app(SettingsCoercer::class)->coerce($setting, $value));

        $row = SiteSetting::firstOrNew(['handle' => $handle]);
        $row->values = [...($row->values ?? []), $field => $coerced];
        $row->save();

        unset($this->resolved[$handle]);

        return $coerced;
    }

    public function forgetResolved(): void
    {
        $this->resolved = [];
    }

    /**
     * A section takes its defaults from its constructor; a site settings group
     * has no constructor, so ->default() on a top-level field applies here.
     *
     * @return array<string, mixed>
     */
    private function storedWithDefaults(SiteSettingsGroup $group): array
    {
        $stored = SiteSetting::where('handle', $group->handle)->value('values') ?? [];

        foreach ($group->schema->fields() as $field) {
            if ($field->hasNestedDefault && ! array_key_exists($field->name, $stored)) {
                $stored[$field->name] = $field->nestedDefault;
            }
        }

        return $stored;
    }

    private function requireGroup(string $handle): SiteSettingsGroup
    {
        return $this->groups[$handle]
            ?? throw new InvalidArgumentException("No site settings group [{$handle}] is registered.");
    }
}
