<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Enums;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;

/**
 * The type of the host's user primary key, from config('inlay.user_key_type').
 * Inlay's migrations size the columns that record a user (created_by,
 * updated_by) from it. Those columns have no foreign key, because Inlay does
 * not know the host's users table.
 */
enum UserKeyType: string
{
    case Int = 'int';
    case Uuid = 'uuid';
    case Ulid = 'ulid';
    case String = 'string';

    public static function configured(): self
    {
        return self::from((string) config('inlay.user_key_type', 'int'));
    }

    public function column(Blueprint $table, string $name): ColumnDefinition
    {
        return match ($this) {
            self::Int => $table->unsignedBigInteger($name),
            self::Uuid => $table->uuid($name),
            self::Ulid => $table->ulid($name),
            self::String => $table->string($name),
        };
    }
}
