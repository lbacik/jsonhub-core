<?php

declare(strict_types=1);

namespace spec\JsonHub\Core;

use JsonHub\Core\FilterCriteria;
use PhpSpec\ObjectBehavior;

class FilterCriteriaSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldHaveType(FilterCriteria::class);
    }

    public function it_has_default_pagination(): void
    {
        $this->offset->shouldBe(0);
        $this->limit->shouldBe(10);
    }

    public function it_allows_private_items_for_an_owner(): void
    {
        $this->beConstructedWith(null, null, null, null, 'user-id', true);
        $this->shouldHaveType(FilterCriteria::class);
    }

    public function it_requires_an_owner_for_private_items(): void
    {
        $this->beConstructedWith(null, null, null, null, null, true);
        $this->shouldThrow(\InvalidArgumentException::class)->duringInstantiation();
    }
}
