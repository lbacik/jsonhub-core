<?php

declare(strict_types=1);

namespace JsonHub\Core\Exceptions;

use InvalidArgumentException;

class CreateDefinitionException extends InvalidArgumentException
{
    public const NOT_ENOUGH_DATA = 1;
    public const PARENT_MISMATCH = 2;
    public const OWNER_PARENT_PAIRING = 3;

    public static function notEnoughData(): self
    {
        return new self('Fields Data, Owner and Parent are required', self::NOT_ENOUGH_DATA);
    }

    public static function parentMismatch(): self
    {
        return new self('Parent owner is not the same as definition owner', self::PARENT_MISMATCH);
    }

    public static function ownerParentPairing(): self
    {
        return new self('Owner and Parent must either both be set or both be empty', self::OWNER_PARENT_PAIRING);
    }
}
