<?php

declare(strict_types=1);

namespace JsonHub\Core\CanonicalJson;

use JsonHub\Core\CanonicalJson\Internal\Reader;

/**
 * A JSON value in canonical form (an RFC 8785 subset): object members
 * sorted by UTF-16 code units, array order preserved, no whitespace,
 * raw UTF-8, "/" never escaped, numbers as ECMAScript Number::toString.
 *
 * Read with strict() for new writes (rejects duplicate members and
 * numbers that are not losslessly representable as IEEE doubles) or with
 * legacy() for stored data (accepts everything without altering it).
 * JSON value equality is byte-identity of the canonical form.
 */
final class CanonicalJson
{
    private function __construct(
        private readonly string $bytes,
        private readonly ChargeEncoding $encoding,
    ) {
    }

    /**
     * @throws CanonicalJsonException
     */
    public static function strict(string $json): self
    {
        [$bytes, $legacyUsed, ] = (new Reader(true))->read($json);

        return new self($bytes, $legacyUsed ? ChargeEncoding::Legacy : ChargeEncoding::Jcs);
    }

    /**
     * @throws CanonicalJsonException
     */
    public static function legacy(string $json): self
    {
        [$bytes, $legacyUsed, ] = (new Reader(false))->read($json);

        return new self($bytes, $legacyUsed ? ChargeEncoding::Legacy : ChargeEncoding::Jcs);
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function encoding(): ChargeEncoding
    {
        return $this->encoding;
    }

    public function equals(CanonicalJson $other): bool
    {
        return $this->bytes === $other->bytes;
    }
}
