<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Schema;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Richtext = 'richtext';
    case Number = 'number';
    case Toggle = 'toggle';
    case Image = 'image';
    case Select = 'select';
    case Link = 'link';
    case Repeater = 'repeater';
    case Group = 'group';
    case Blocks = 'blocks';
}
