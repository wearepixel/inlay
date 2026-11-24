<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use WeArePixel\Inlay\Enums\UserKeyType;

/**
 * The earlier migrations created created_by and updated_by as bigints. This
 * changes them to config('inlay.user_key_type') when that is not 'int', so
 * apps whose users have UUID, ULID or string keys can record who acted.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'page_revisions' => ['created_by'],
        'declarative_sections' => ['created_by', 'updated_by'],
    ];

    public function up(): void
    {
        $type = UserKeyType::configured();

        if ($type === UserKeyType::Int) {
            return;
        }

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $this->change($table, $column, $type);
            }
        }
    }

    public function down(): void
    {
        // Left as is: a UUID, ULID or string id already stored may not fit a bigint.
    }

    private function change(string $table, string $column, UserKeyType $type): void
    {
        $connection = Schema::getConnection();

        // Postgres has no implicit cast from bigint to uuid, and Blueprint's
        // using() for change() only exists from Laravel 13.
        if ($type === UserKeyType::Uuid && $connection->getDriverName() === 'pgsql') {
            $grammar = $connection->getQueryGrammar();
            $wrapped = $grammar->wrap($column);

            $connection->statement("alter table {$grammar->wrapTable($table)} alter column {$wrapped} type uuid using {$wrapped}::text::uuid");

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($type, $column) {
            $type->column($blueprint, $column)->nullable()->change();
        });
    }
};
