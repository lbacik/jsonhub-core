<?php

declare(strict_types=1);

namespace JsonHub\Core\ValuesFactory;

use JsonHub\Core\Exceptions\CreateSlugException;
use Sushi\ValueObject;
use Sushi\ValueObject\Invariant;

class Slug extends ValueObject
{
    public const MAX_LENGTH = 64;

    public function __construct(
        public string | null $value,
    ) {
        parent::__construct();
    }

    #[Invariant]
    public function validator(): void
    {
        if ($this->value === null) {
            return;
        }

        if (preg_match('/^[a-z0-9-]+$/', $this->value) !== 1) {
            throw CreateSlugException::invalidInput($this->value);
        }
    }

    #[Invariant]
    protected function checkLengthLimit(): void
    {
        if ($this->value && strlen($this->value) > self::MAX_LENGTH) {
            throw CreateSlugException::tooLong(strlen($this->value));
        }
    }
}
