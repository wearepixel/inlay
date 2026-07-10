<?php

declare(strict_types=1);

namespace WearePixel\BladePageBuilder\Schema;

enum FieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Richtext = 'richtext';
    case Image = 'image';
    case Select = 'select';
    case Link = 'link';
    case Repeater = 'repeater';
    case Group = 'group';
}
