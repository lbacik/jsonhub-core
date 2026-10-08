<?php

declare(strict_types=1);

namespace spec\JsonHub\Core;

use JsonHub\Contracts\Definition;
use JsonHub\Contracts\Entity;
use JsonHub\Contracts\JsonValidator;
use JsonHub\Contracts\User;
use JsonHub\Core\EntityFactory;
use JsonHub\Core\Types\Entity as EntityValues;
use JsonHub\Core\Types\Json;
use JsonHub\Core\Types\Metadata\EntityInputField;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class EntityFactorySpec extends ObjectBehavior
{
    private const EMPTY_JSON_OBJECT_AS_STRING = '{}';

    public function let(
        JsonValidator $jsonValidator,
        User $user,
        User $otherUser,
    ) {
        $this->beConstructedWith($jsonValidator);

        $user->getId()->willReturn('user-id');
        $otherUser->getId()->willReturn('other-user-id');
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(EntityFactory::class);
    }

    public function it_throws_exception_when_creating_entity_values_without_data(): void
    {
        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('createEntity', [[]]);
    }

    public function it_creates_entity_values_with_minimal_input(
        JsonValidator $jsonValidator,
        User $user,
        Definition $definition,
    ): void {
        $jsonValidator
            ->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING), Argument::type(Json::class))
            ->willReturn(true);

        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);

        $this->createEntity([
            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            EntityInputField::OWNER->value => $user,
            EntityInputField::DEFINITION->value => $definition,
        ])->shouldBeAnInstanceOf(EntityValues::class);
    }

    public function it_throws_exception_when_creating_entity_values_with_invalid_json(
        JsonValidator $jsonValidator,
        User $user,
        Definition $definition,
    ): void {
//        $jsonValidator
//            ->validate(self::EMPTY_JSON_OBJECT_AS_STRING)
//            ->willReturn(false);

        $this->shouldThrow(\JsonException::class)
            ->during('createEntity', [
            [
                EntityInputField::DATA->value => "['invalid']",
                EntityInputField::OWNER->value => $user,
                EntityInputField::DEFINITION->value => $definition,
            ],
        ]);
    }

    public function it_creates_entity_values(
        JsonValidator $jsonValidator,
        User $user,
        Definition $definition,
        Entity $parent,
    ): void {
        $jsonValidator
            ->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING), Argument::type(Json::class))
            ->willReturn(true);

        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);
        $parent->getOwner()->willReturn($user);

        $this->createEntity([
            EntityInputField::SLUG->value => 'slug',
            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            EntityInputField::OWNER->value => $user,
            EntityInputField::DEFINITION->value => $definition,
            EntityInputField::PARENT->value => $parent,
            EntityInputField::PRIVATE->value => false,
        ])->shouldBeAnInstanceOf(EntityValues::class);
    }

    public function it_throws_exception_when_entity_owner_do_not_match_parent_one(
        JsonValidator $jsonValidator,
        User $user,
        User $otherUser,
        Definition $definition,
        Entity $parent,
    ): void {
        $jsonValidator
            ->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING), Argument::type(Json::class))
            ->willReturn(true);

        $parent->getOwner()->willReturn($otherUser);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('createEntity', [
            [
                EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
                EntityInputField::OWNER->value => $user,
                EntityInputField::DEFINITION->value => $definition,
                EntityInputField::PARENT->value => $parent,
            ],
        ]);
    }
}
