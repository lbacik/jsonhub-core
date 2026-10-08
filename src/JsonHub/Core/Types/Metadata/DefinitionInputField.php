<?php

declare(strict_types=1);

namespace JsonHub\Core\Types\Metadata;

enum DefinitionInputField: string
{
    case SLUG = 'slug';
    case DATA = 'data';
    case PARENT = 'parent';
    case OWNER = 'owner';
}
