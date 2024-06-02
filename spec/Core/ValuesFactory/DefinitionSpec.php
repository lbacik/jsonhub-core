<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\ValuesFactory;

use JsonHub\Contracts\Entity;
use JsonHub\Contracts\User;
use JsonHub\Core\Exceptions\CreateDefinitionException;
use JsonHub\Core\ValuesFactory\Definition;
use JsonHub\Core\ValuesFactory\JsonSchema;
use JsonHub\Core\ValuesFactory\Slug;
use PhpSpec\ObjectBehavior;

class DefinitionSpec extends ObjectBehavior
{
    public function let(
        User $owner,
        User $parentOwner,
    ): void {
        $owner->getId()->willReturn('user-id');
        $parentOwner->getId()->willReturn('parent-user-id');
    }

    public function it_is_initializable(
        JsonSchema $jsonSchema,
        Entity $parent,
        User $owner,
    ) {
        $parent->getOwner()->willReturn($owner);

        $this->beConstructedWith(
            new Slug(null),
            $jsonSchema,
            $parent,
            $owner,
        );

        $this->shouldHaveType(Definition::class);
    }

    public function it_should_throw_exception_if_parent_owner_is_not_same_as_definition_owner(
        JsonSchema $jsonSchema,
        Entity $parent,
        User $owner,
        User $parentOwner,
    ) {
        $parent->getOwner()->willReturn($parentOwner);

        $this->beConstructedWith(
            new Slug(null),
            $jsonSchema,
            $parent,
            $owner,
        );

        $this->shouldThrow(CreateDefinitionException::class)->duringInstantiation();
    }
}
