<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\Versioning;

use JsonHub\Contracts\Entity;
use JsonHub\Contracts\User;
use JsonHub\Core\Exceptions\CreateDefinitionException;
use JsonHub\Core\Versioning\DefinitionPlacement;
use PhpSpec\ObjectBehavior;

class DefinitionPlacementSpec extends ObjectBehavior
{
    public function it_accepts_a_root_definition(
        User $owner,
        Entity $parent,
    ): void {
        $this->beConstructedWith(null, null);
        $this->shouldHaveType(DefinitionPlacement::class);
    }

    public function it_accepts_an_owner_with_a_parent_of_the_same_owner(
        User $owner,
        Entity $parent,
    ): void {
        $owner->getId()->willReturn('user-id');
        $parent->getOwner()->willReturn($owner);

        $this->beConstructedWith($owner, $parent);
        $this->shouldHaveType(DefinitionPlacement::class);
    }

    public function it_rejects_an_owner_without_a_parent(
        User $owner,
    ): void {
        $this->beConstructedWith($owner, null);
        $exception = new CreateDefinitionException(
            'Owner and Parent must either both be set or both be empty',
            CreateDefinitionException::OWNER_PARENT_PAIRING,
        );
        $this->shouldThrow($exception)->duringInstantiation();
    }

    public function it_rejects_a_parent_without_an_owner(
        Entity $parent,
    ): void {
        $this->beConstructedWith(null, $parent);
        $exception = new CreateDefinitionException(
            'Owner and Parent must either both be set or both be empty',
            CreateDefinitionException::OWNER_PARENT_PAIRING,
        );
        $this->shouldThrow($exception)->duringInstantiation();
    }

    public function it_rejects_a_parent_of_another_owner(
        User $owner,
        User $parentOwner,
        Entity $parent,
    ): void {
        $owner->getId()->willReturn('user-id');
        $parentOwner->getId()->willReturn('other-user-id');
        $parent->getOwner()->willReturn($parentOwner);

        $this->beConstructedWith($owner, $parent);
        $exception = new CreateDefinitionException(
            'Parent owner is not the same as definition owner',
            CreateDefinitionException::PARENT_MISMATCH,
        );
        $this->shouldThrow($exception)->duringInstantiation();
    }
}
