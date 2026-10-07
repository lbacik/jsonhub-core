<?php

declare(strict_types=1);

namespace JsonHub\Core\CanonicalJson;

use InvalidArgumentException;

/**
 * A host-supplied byte limit checked against a schema charge. Core does
 * not define the limit value. Charges with encoding 0 are exempt.
 */
final class DraftSizeLimit
{
    public readonly int $bytes;

    public function __construct(int $bytes)
    {
        if ($bytes < 0) {
            throw new InvalidArgumentException('Draft size limit cannot be negative');
        }
        $this->bytes = $bytes;
    }

    /**
     * @throws CanonicalJsonException
     */
    public function check(SchemaCharge $charge): void
    {
        if ($charge->encoding === ChargeEncoding::Jcs && $charge->bytes > $this->bytes) {
            throw CanonicalJsonException::single(CanonicalJsonException::SCHEMA_TOO_LARGE, '');
        }
    }
}
