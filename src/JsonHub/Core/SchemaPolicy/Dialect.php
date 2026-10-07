<?php

declare(strict_types=1);

namespace JsonHub\Core\SchemaPolicy;

enum Dialect: string
{
    case Legacy = 'legacy';
    case Draft04 = 'draft-04';
}
