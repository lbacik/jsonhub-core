<?php

declare(strict_types=1);

namespace JsonHub\Core\CanonicalJson;

use InvalidArgumentException;

class CanonicalJsonException extends InvalidArgumentException
{
    public const DUPLICATE_MEMBER = 'duplicate_member';
    public const UNSAFE_INTEGER = 'unsafe_integer';
    public const NUMBER_OVERFLOW = 'number_overflow';
    public const PRECISION_LOSS = 'precision_loss';
    public const INVALID_STRING = 'invalid_string';
    public const INVALID_JSON = 'invalid_json';
    public const ROOT_NOT_OBJECT = 'root_not_object';
    public const SCHEMA_TOO_LARGE = 'schema_too_large';

    /** @var list<Violation> */
    public readonly array $violations;

    /**
     * @param list<Violation> $violations
     */
    protected function __construct(array $violations)
    {
        $this->violations = $violations;
        parent::__construct(self::buildMessage($violations));
    }

    /**
     * @param list<Violation> $violations
     */
    public static function fromViolations(array $violations): self
    {
        return new self(array_values($violations));
    }

    public static function single(string $code, string $pointer): self
    {
        return new self([new Violation($code, $pointer)]);
    }

    /**
     * @param list<Violation> $violations
     */
    private static function buildMessage(array $violations): string
    {
        $parts = [];
        foreach ($violations as $violation) {
            $parts[] = $violation->code . ' at '
                . ($violation->pointer === '' ? '(root)' : $violation->pointer);
        }

        return 'Invalid canonical JSON: ' . implode('; ', $parts);
    }
}
