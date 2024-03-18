<?php

declare(strict_types=1);

namespace JsonHub\Core\ValuesFactory;

enum DefinitionInputField: string
{
    case SLUG = 'slug';
    case DATA = 'data';
    case PARENT = 'parent';
    case OWNER = 'owner';
}
