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

    public function it_defaults_definition_revision_to_null(): void
    {
        $this->definitionRevision->shouldBe(null);
    }

    public function it_accepts_a_definition_revision_as_the_last_argument(): void
    {
        $this->beConstructedWith(null, null, null, null, null, null, 0, 10, 'revision-id');
        $this->definitionRevision->shouldBe('revision-id');
    }
}
