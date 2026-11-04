<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\ArrayType;

final class SectionsSchema
{
    public static function make(JsonSchema $schema): ArrayType
    {
        return $schema->array()->items($schema->object([
            'block' => $schema->string()->description('Block handle from inlay-list-blocks.')->required(),
            'settings' => $schema->object()->description('Setting values keyed by setting name. Omit a setting (or send null) to use its default.'),
        ]));
    }
}
