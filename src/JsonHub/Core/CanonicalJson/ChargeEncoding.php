<?php

declare(strict_types=1);

namespace JsonHub\Core\CanonicalJson;

/**
 * How a canonical value relates to the new-write rules.
 *
 * Jcs means the value passes Strict Reading: it is the RFC 8785 (JCS)
 * subset form. Legacy means the value was read by Legacy Reading and uses
 * at least one legacy exception (duplicate members, non-lossless numbers,
 * lone surrogates), so it is outside the new-write rules.
 */
enum ChargeEncoding: int
{
    case Legacy = 0;
    case Jcs = 1;
}
