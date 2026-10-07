<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\CanonicalJson;

use JsonHub\Core\CanonicalJson\ChargeEncoding;
use PhpSpec\ObjectBehavior;

class ChargeEncodingSpec extends ObjectBehavior
{
    public function it_has_legacy_and_jcs_cases(): void
    {
        if (ChargeEncoding::Legacy->value !== 0) {
            throw new \RuntimeException('Expected Legacy to be 0');
        }
        if (ChargeEncoding::Jcs->value !== 1) {
            throw new \RuntimeException('Expected Jcs to be 1');
        }
        if (ChargeEncoding::from(0) !== ChargeEncoding::Legacy) {
            throw new \RuntimeException('Expected from(0) to be Legacy');
        }
        if (ChargeEncoding::from(1) !== ChargeEncoding::Jcs) {
            throw new \RuntimeException('Expected from(1) to be Jcs');
        }
    }
}
