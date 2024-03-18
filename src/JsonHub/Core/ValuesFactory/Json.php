<?php

declare(strict_types=1);

namespace JsonHub\Core\ValuesFactory;

use Sushi\ValueObject;
use Sushi\ValueObject\Invariant;

class Json extends ValueObject
{
    public function __construct(
        public string $value,
    ) {
        parent::__construct();
    }

    #[Invariant]
    public function isValidJson(): void
    {
        json_decode($this->value, flags: JSON_THROW_ON_ERROR);
    }
}
