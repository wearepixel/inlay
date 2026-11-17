<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per registered site settings group, holding only the fields an
 * editor has changed - the rest resolve from the group's defaults.
 *
 * @property string $handle
 * @property array<string, mixed>|null $values
 */
final class SiteSetting extends Model
{
    protected $table = 'inlay_site_settings';

    protected $fillable = ['handle', 'values'];

    protected function casts(): array
    {
        return ['values' => 'array'];
    }
}
