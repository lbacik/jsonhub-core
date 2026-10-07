<?php

declare(strict_types=1);

namespace JsonHub\Core\CanonicalJson;

/**
 * The charge of a canonical schema: the UTF-8 byte length of its canonical
 * form plus its charge encoding. Building a charge for encoding 1 requires
 * an object root; encoding 0 (legacy-lossless) is exempt.
 */
final class SchemaCharge
{
    private function __construct(
        public readonly int $bytes,
        public readonly ChargeEncoding $encoding,
    ) {
    }

    /**
     * @throws CanonicalJsonException
     */
    public static function of(CanonicalJson $json): self
    {
        if ($json->encoding() === ChargeEncoding::Jcs && !str_starts_with($json->bytes(), '{')) {
            throw CanonicalJsonException::single(CanonicalJsonException::ROOT_NOT_OBJECT, '');
        }

        return new self(strlen($json->bytes()), $json->encoding());
    }
}
