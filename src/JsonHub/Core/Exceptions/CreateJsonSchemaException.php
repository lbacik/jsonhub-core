<?php

declare(strict_types=1);

namespace JsonHub\Core\Exceptions;

use InvalidArgumentException;

class CreateJsonSchemaException extends InvalidArgumentException
{
    public const NOT_VALID_DATA = 1;

    public static function notValidData(string $error): self
    {
        return new self(
            'Data is not valid - ' . $error,
            self::NOT_VALID_DATA
        );
    }
}
