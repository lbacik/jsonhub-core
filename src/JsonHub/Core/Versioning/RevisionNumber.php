<?php

declare(strict_types=1);

namespace JsonHub\Core\Versioning;

use Sushi\ValueObject;
use Sushi\ValueObject\Invariant;

class RevisionNumber extends ValueObject
{
    public function __construct(
        public readonly int $value,
    ) {
        parent::__construct();
    }

    #[Invariant]
    public function mustBePositive(): void
    {
        if ($this->value < 1) {
            throw new \InvalidArgumentException('Revision number must be a positive integer');
        }
    }

    public static function first(): self
    {
        return new self(1);
    }

    public function next(): self
    {
        return new self($this->value + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function value(): int
    {
        return $this->value;
    }
}
