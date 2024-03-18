<?php

declare(strict_types=1);

namespace JsonHub\Core\Exceptions;

use InvalidArgumentException;

class CreateSlugException extends InvalidArgumentException
{
    public static function invalidInput(string $input): self
    {
        return new self("Invalid slug: $input");
    }

    public static function tooLong(int $length): self
    {
        return new self("Slug is too long: $length characters");
    }
}
