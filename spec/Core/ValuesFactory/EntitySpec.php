<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\ValuesFactory;

use JsonHub\Contracts\Definition;
use JsonHub\Contracts\Entity;
use JsonHub\Contracts\JsonValidator;
use JsonHub\Contracts\User;
use JsonHub\Core\Exceptions\CreateEntityException;
use JsonHub\Core\ValuesFactory\Entity as EntityValues;
use JsonHub\Core\ValuesFactory\Json;
use JsonHub\Core\ValuesFactory\Slug;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class EntitySpec extends ObjectBehavior
{
    public function let(
        User $owner,
        User $parentOwner,
    ): void {
        $owner->getId()->willReturn('user-id');
        $parentOwner->getId()->willReturn('parent-user-id');
    }

    public function it_is_initializable(
        Definition $definition,
        User $owner,
        JsonValidator $jsonValidator,
    ) {
        $emptyJson = new Json("{}");
        $definition->getData()->willReturn("null");
        $jsonValidator->isValid($emptyJson, Argument::type(Json::class))->willReturn(true);

        $this->beConstructedWith(
            new Slug(null),
            $emptyJson,
            $definition,
            null,
            $owner,
            false,
            $jsonValidator,
        );

        $this->shouldHaveType(EntityValues::class);
    }

    public function it_should_throw_exception_if_data_is_not_correct_to_definition(
        Definition $definition,
        User $owner,
        JsonValidator $jsonValidator,
    ) {
        $invalidJson = new Json("{}");
        $definition->getData()->willReturn("null");
        $jsonValidator->isValid($invalidJson, Argument::type(Json::class))->willReturn(false);
        $jsonValidator->getError()->willReturn("Invalid Input");

        $this->beConstructedWith(
            new Slug(null),
            $invalidJson,
            $definition,
            null,
            $owner,
            false,
            $jsonValidator,
        );

        $this->shouldThrow(CreateEntityException::class)->duringInstantiation();
    }

    public function it_should_throw_exception_if_parent_owner_is_not_same_as_entity_owner(
        Definition $definition,
        User $owner,
        User $parentOwner,
        Entity $parent,
        JsonValidator $jsonValidator,
    ) {
        $emptyJson = new Json("{}");
        $definition->getData()->willReturn("null");
        $jsonValidator->isValid($emptyJson, Argument::type(Json::class))->willReturn(true);
        $parent->getOwner()->willReturn($parentOwner);

        $this->beConstructedWith(
            new Slug(null),
            $emptyJson,
            $definition,
            $parent,
            $owner,
            false,
            $jsonValidator,
        );

        $this->shouldThrow(CreateEntityException::class)->duringInstantiation();
    }
}
