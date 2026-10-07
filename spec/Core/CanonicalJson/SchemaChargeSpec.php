<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\CanonicalJson;

use JsonHub\Core\CanonicalJson\CanonicalJson;
use JsonHub\Core\CanonicalJson\CanonicalJsonException;
use JsonHub\Core\CanonicalJson\ChargeEncoding;
use JsonHub\Core\CanonicalJson\SchemaCharge;
use PhpSpec\ObjectBehavior;

class SchemaChargeSpec extends ObjectBehavior
{
    public function it_measures_the_utf8_byte_length_of_the_canonical_form(): void
    {
        $charge = SchemaCharge::of(CanonicalJson::strict('{"a":1}'));
        if ($charge->bytes !== 7) {
            throw new \RuntimeException('Expected 7 bytes, got ' . $charge->bytes);
        }
        if ($charge->encoding !== ChargeEncoding::Jcs) {
            throw new \RuntimeException('Expected encoding 1');
        }

        $multibyte = SchemaCharge::of(CanonicalJson::strict('{"é":1}'));
        if ($multibyte->bytes !== 8) {
            throw new \RuntimeException('Expected 8 bytes, got ' . $multibyte->bytes);
        }
    }

    public function it_requires_an_object_root_for_encoding_1(): void
    {
        foreach (['"a"', '[1]', '1', 'true', 'null'] as $input) {
            try {
                SchemaCharge::of(CanonicalJson::strict($input));
            } catch (CanonicalJsonException $exception) {
                if (count($exception->violations) !== 1) {
                    throw new \RuntimeException('Expected a single violation for ' . $input);
                }
                $violation = $exception->violations[0];
                if ($violation->code !== 'root_not_object' || $violation->pointer !== '') {
                    throw new \RuntimeException('Unexpected violation for ' . $input);
                }
                continue;
            }
            throw new \RuntimeException('Expected root_not_object for ' . $input);
        }
    }

    public function it_exempts_encoding_0_from_the_object_rule(): void
    {
        $charge = SchemaCharge::of(CanonicalJson::legacy('9007199254740993'));
        if ($charge->bytes !== 16 || $charge->encoding !== ChargeEncoding::Legacy) {
            throw new \RuntimeException('Unexpected exempt charge');
        }
    }
}
