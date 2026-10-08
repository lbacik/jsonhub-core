<?php

declare(strict_types=1);

namespace JsonHub\Core\Types\Metadata;

enum EntityInputField: string
{
    case SLUG = 'slug';
    case DATA = 'data';
    case DEFINITION = 'definition';
    case PARENT = 'parent';
    case OWNER = 'owner';
    case PRIVATE = 'private';
}
