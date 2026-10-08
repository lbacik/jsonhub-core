<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\Versioning;

use JsonHub\Core\Versioning\RevisionNumber;
use PhpSpec\ObjectBehavior;

class RevisionNumberSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->beConstructedWith(1);
        $this->shouldHaveType(RevisionNumber::class);
    }

    public function it_rejects_zero(): void
    {
        $this->beConstructedWith(0);
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }

    public function it_rejects_negative_numbers(): void
    {
        $this->beConstructedWith(-1);
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }

    public function it_starts_at_one(): void
    {
        $this->beConstructedThrough('first', []);
        $this->value()->shouldBe(1);
    }

    public function it_advances_to_the_next_number(): void
    {
        $this->beConstructedWith(3);
        $this->next()->shouldBeLike(new RevisionNumber(4));
    }

    public function it_compares_by_value(): void
    {
        $this->beConstructedWith(2);
        $this->equals(new RevisionNumber(2))->shouldBe(true);
        $this->equals(new RevisionNumber(3))->shouldBe(false);
    }
}
