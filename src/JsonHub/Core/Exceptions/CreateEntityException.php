<?php

declare(strict_types=1);

namespace JsonHub\Core\Exceptions;

use InvalidArgumentException;

class CreateEntityException extends InvalidArgumentException
{
    public const NOT_VALID_DATA = 1;
    public const PARENT_OWNER_MISMATCH = 2;

    public static function notValidData(string $error): self
    {
        return new self("Can't create Entity - not valid data: $error", self::NOT_VALID_DATA);
    }

    public static function parentOwnerMismatch(): self
    {
        return new self('Parent owner is not the same as entity owner', self::PARENT_OWNER_MISMATCH);
    }
}
