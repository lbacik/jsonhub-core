<?php

declare(strict_types=1);

namespace spec\JsonHub\Core;

use JsonHub\Contracts\Definition;
use JsonHub\Contracts\Entity;
use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Contracts\JsonValidator;
use JsonHub\Contracts\User;
use JsonHub\Core\Exceptions\CreateDefinitionException;
use JsonHub\Core\Exceptions\CreateJsonSchemaException;
use JsonHub\Core\ValuesFactory;
use JsonHub\Core\ValuesFactory\DefinitionInputField;
use JsonHub\Core\ValuesFactory\Definition as DefinitionValues;
use JsonHub\Core\ValuesFactory\EntityInputField;
use JsonHub\Core\ValuesFactory\Entity as EntityValues;
use JsonHub\Core\ValuesFactory\Json;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ValuesFactorySpec extends ObjectBehavior
{
    private const EMPTY_JSON_OBJECT_AS_STRING = '{}';

    public function let(
        JsonValidator $jsonValidator,
        JsonSchemaValidator $jsonSchemaValidator,
    ) {
        $this->beConstructedWith(
            $jsonValidator,
            $jsonSchemaValidator,
        );
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(ValuesFactory::class);
    }

    public function it_throws_exception_when_creating_definition_values_without_data(): void
    {
        $this->shouldThrow(CreateDefinitionException::notEnoughData())
            ->during('createDefinition', [[]]);
    }

    public function it_creates_definition_values_with_minimal_input(
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        Entity $parent,
    ): void {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))
            ->willReturn(true);

        $parent->getOwner()->willReturn($user);

        $this->createDefinition([
            DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            DefinitionInputField::OWNER->value => $user->getWrappedObject(),
            DefinitionInputField::PARENT->value => $parent,
        ])->shouldBeAnInstanceOf(DefinitionValues::class);
    }

    public function it_throws_exception_when_creating_definition_values_with_invalid_json(
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        Entity $parent,
    ): void {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))
            ->willReturn(false);
        $jsonSchemaValidator->getError()->willReturn('error');

        $this->shouldThrow(CreateJsonSchemaException::notValidData('error'))
            ->during('createDefinition', [
            [
                DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
                DefinitionInputField::OWNER->value => $user,
                DefinitionInputField::PARENT->value => $parent,
            ],
        ]);
    }

    public function it_creates_definition_values(
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        Entity $parent,
    ): void {
        $jsonSchemaValidator
            ->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))
            ->willReturn(true);

        $parent->getOwner()->willReturn($user);

        $this->createDefinition([
            DefinitionInputField::SLUG->value => 'slug',
            DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            DefinitionInputField::OWNER->value => $user,
            DefinitionInputField::PARENT->value => $parent,
        ])->shouldBeAnInstanceOf(DefinitionValues::class);
    }

    public function it_throws_exception_when_definition_owner_do_not_match_parent_one(
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        User $otherUser,
        Entity $parent,
    ): void {
        $jsonSchemaValidator
            ->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))
            ->willReturn(true);

        $parent->getOwner()->willReturn($otherUser);

        $this->shouldThrow(CreateDefinitionException::parentMismatch())
            ->during('createDefinition', [
            [
                DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
                DefinitionInputField::OWNER->value => $user,
                DefinitionInputField::PARENT->value => $parent,
            ],
        ]);
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
