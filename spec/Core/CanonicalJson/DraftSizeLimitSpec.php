<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\CanonicalJson;

use JsonHub\Core\CanonicalJson\CanonicalJson;
use JsonHub\Core\CanonicalJson\CanonicalJsonException;
use JsonHub\Core\CanonicalJson\DraftSizeLimit;
use JsonHub\Core\CanonicalJson\SchemaCharge;
use PhpSpec\ObjectBehavior;

class DraftSizeLimitSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->beConstructedWith(1048576);
        $this->shouldHaveType(DraftSizeLimit::class);
    }

    public function it_accepts_a_charge_at_exactly_the_limit(): void
    {
        $charge = SchemaCharge::of(CanonicalJson::strict('{"a":1}'));
        $this->beConstructedWith($charge->bytes);
        $this->check($charge)->shouldReturn(null);
    }

    public function it_rejects_a_charge_one_byte_over_the_limit(): void
    {
        $charge = SchemaCharge::of(CanonicalJson::strict('{"a":1}'));
        $this->beConstructedWith($charge->bytes - 1);
        $this->shouldThrow(CanonicalJsonException::class)->during('check', [$charge]);
    }

    public function it_reports_schema_too_large_at_the_root(): void
    {
        $charge = SchemaCharge::of(CanonicalJson::strict('{"a":1}'));
        try {
            (new DraftSizeLimit($charge->bytes - 1))->check($charge);
        } catch (CanonicalJsonException $exception) {
            $violation = $exception->violations[0];
            if ($violation->code !== 'schema_too_large' || $violation->pointer !== '') {
                throw new \RuntimeException('Unexpected violation');
            }

            return;
        }
        throw new \RuntimeException('Expected schema_too_large to be thrown');
    }

    public function it_exempts_encoding_0_regardless_of_size(): void
    {
        $charge = SchemaCharge::of(CanonicalJson::legacy('{"a":1,"a":2}'));
        $this->beConstructedWith(0);
        $this->check($charge)->shouldReturn(null);
    }

    public function it_rejects_a_negative_limit(): void
    {
        $this->beConstructedWith(-1);
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }
}
