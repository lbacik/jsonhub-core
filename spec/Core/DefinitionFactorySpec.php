<?php

declare(strict_types=1);

namespace spec\JsonHub\Core;

use JsonHub\Contracts\Entity;
use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Contracts\User;
use JsonHub\Core\DefinitionFactory;
use JsonHub\Core\Exceptions\CreateDefinitionException;
use JsonHub\Core\Exceptions\CreateJsonSchemaException;
use JsonHub\Core\JsonSchemaFactory;
use JsonHub\Core\Types\Definition as DefinitionValues;
use JsonHub\Core\Types\Json;
use JsonHub\Core\Types\Metadata\DefinitionInputField;
use PhpSpec\ObjectBehavior;

class DefinitionFactorySpec extends ObjectBehavior
{
    private const EMPTY_JSON_OBJECT_AS_STRING = '{}';

    public function let(
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        User $otherUser,
    ) {
        $this->beConstructedWith(
            new JsonSchemaFactory($jsonSchemaValidator->getWrappedObject()),
        );

        $user->getId()->willReturn('user-id');
        $otherUser->getId()->willReturn('other-user-id');
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(DefinitionFactory::class);
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
}
