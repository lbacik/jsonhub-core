<?php

declare(strict_types=1);

namespace JsonHub\Core\SchemaPolicy;

final readonly class SchemaViolation
{
    public function __construct(
        public SchemaViolationCode $code,
        public string $pointer,
        public string $message,
    ) {
    }
}
