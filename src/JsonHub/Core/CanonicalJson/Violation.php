<?php

declare(strict_types=1);

namespace JsonHub\Core\CanonicalJson;

/**
 * One reason a JSON text, charge or limit check fails.
 *
 * The code is stable (part of the domain contract); the pointer is an
 * RFC 6901 JSON Pointer into the canonicalized value ("" for the root).
 * For malformed input (invalid_json) the pointer names the enclosing value
 * with a "#"-suffixed byte offset, e.g. "/a#8" or "#0" for the root.
 */
final readonly class Violation
{
    public function __construct(
        public string $code,
        public string $pointer,
    ) {
    }
}
