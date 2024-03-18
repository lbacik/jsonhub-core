<?php

declare(strict_types=1);

namespace JsonHub\Core\Exceptions;

use InvalidArgumentException;

class CreateDefinitionException extends InvalidArgumentException
{
    public const NOT_ENOUGH_DATA = 1;
    public const PARENT_MISMATCH = 2;

    public static function notEnoughData(): self
    {
        return new self('Fields Data, Owner and Parent are required', self::NOT_ENOUGH_DATA);
    }

    public static function parentMismatch(): self
    {
        return new self('Parent owner is not the same as definition owner', self::PARENT_MISMATCH);
    }
}
