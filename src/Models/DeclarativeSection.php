<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $handle
 * @property array<string, mixed> $definition
 * @property int|string|null $created_by
 * @property int|string|null $updated_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class DeclarativeSection extends Model
{
    protected $fillable = ['handle', 'definition', 'created_by', 'updated_by'];

    protected $casts = [
        'definition' => 'array',
    ];
}
