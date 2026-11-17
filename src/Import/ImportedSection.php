<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Import;

/**
 * One section worked out by BladePageImporter, ready to be written as a
 * single-file section.
 */
final readonly class ImportedSection
{
    /**
     * @param  array<int, array{name: string, type: string, label: string, default: mixed}>  $fields
     */
    public function __construct(
        public string $handle,
        public string $name,
        public string $markup,
        public array $fields,
    ) {}

    public function toBlade(string $sourcePath): string
    {
        $settings = '';

        foreach ($this->fields as $field) {
            $settings .= "\n                ->{$field['type']}('{$field['name']}')->label(".var_export($field['label'], true).')->default('.$this->defaultExpression($field).')';
        }

        $settingsExpression = $settings === '' ? 'Settings::make()' : "Settings::make(){$settings}";
        $description = var_export('Imported from '.$sourcePath.'.', true);
        $name = var_export($this->name, true);

        return <<<BLADE
<?php

use WeArePixel\\Inlay\\Schema\\Settings;
use WeArePixel\\Inlay\\SingleFile\\Section;
use WeArePixel\\Inlay\\Support\\LinkValue;

section(
    Section::make('{$this->handle}')
        ->name({$name})
        ->category('Imported')
        ->description({$description})
        ->settings(
            {$settingsExpression}
        )
);
?>
{$this->markup}

BLADE;
    }

    /**
     * @param  array{name: string, type: string, label: string, default: mixed}  $field
     */
    private function defaultExpression(array $field): string
    {
        if ($field['type'] === 'link' && is_array($field['default'])) {
            return 'new LinkValue('.var_export($field['default']['label'], true).', '.var_export($field['default']['url'], true).')';
        }

        return var_export($field['default'], true);
    }
}
