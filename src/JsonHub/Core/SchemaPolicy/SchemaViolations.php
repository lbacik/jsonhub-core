<?php

declare(strict_types=1);

namespace JsonHub\Core\SchemaPolicy;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, SchemaViolation>
 */
final class SchemaViolations implements Countable, IteratorAggregate
{
    public const MAX_VIOLATIONS = 100;

    /** @var list<SchemaViolation> */
    private readonly array $violations;

    /** @param list<SchemaViolation> $violations */
    public function __construct(array $violations = [])
    {
        $this->violations = array_values(array_slice(array_values($violations), 0, self::MAX_VIOLATIONS));
    }

    public function isEmpty(): bool
    {
        return $this->violations === [];
    }

    public function count(): int
    {
        return count($this->violations);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->violations);
    }

    /** @return list<SchemaViolation> */
    public function toArray(): array
    {
        return $this->violations;
    }
}
