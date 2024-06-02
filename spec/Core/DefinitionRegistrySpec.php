<?php

declare(strict_types=1);

namespace spec\JsonHub\Core;

use JsonHub\Contracts\Definition;
use JsonHub\Contracts\DefinitionRepository;
use JsonHub\Contracts\Entity;
use JsonHub\Contracts\EntityRepository;
use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Contracts\JsonValidator;
use JsonHub\Contracts\User;
use JsonHub\Core\DefinitionRegistry;
use JsonHub\Core\FilterCriteria;
use JsonHub\Core\ValuesFactory;
use JsonHub\Core\ValuesFactory\DefinitionInputField;
use JsonHub\Core\ValuesFactory\Json;
use PhpSpec\ObjectBehavior;

class DefinitionRegistrySpec extends ObjectBehavior
{
    private const EMPTY_JSON_OBJECT_AS_STRING = '{}';

    private ValuesFactory $valuesFactory;

    public function let(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        JsonValidator $jsonValidator,
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        User $otherUser,
    ): void {
        $this->valuesFactory = new ValuesFactory(
            $jsonValidator->getWrappedObject(),
            $jsonSchemaValidator->getWrappedObject(),
        );

        $this->beConstructedWith($definitionRepository, $entityRepository, $this->valuesFactory);

        $user->getId()->willReturn('user-id');
        $otherUser->getId()->willReturn('other-user-id');
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(DefinitionRegistry::class);
    }

    public function it_returns_a_definition(DefinitionRepository $definitionRepository): void
    {
        $definitionRepository->read('definition-id')->shouldBeCalled();
        $this->getDefinition('definition-id')
            ->shouldBeAnInstanceOf(Definition::class);
    }

    public function it_returns_definitions(DefinitionRepository $definitionRepository): void
    {
        $criteria = new FilterCriteria();

        $definitionRepository->readAll($criteria)->shouldBeCalled();
        $this->getDefinitions($criteria);
    }

    public function it_adds_a_definition(
        DefinitionRepository $definitionRepository,
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        Entity $entity,
    ): void {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))->willReturn(true);
        $entity->getOwner()->willReturn($user);

        $definitionValues = $this->valuesFactory->createDefinition([
            DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            DefinitionInputField::OWNER->value => $user->getWrappedObject(),
            DefinitionInputField::PARENT->value => $entity->getWrappedObject(),
        ]);

        $definitionRepository->create($definitionValues)->shouldBeCalled();
        $this->addDefinition($definitionValues)->shouldBeAnInstanceOf(Definition::class);
    }

    public function it_updates_a_definition(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        Definition $definition,
        Entity $parent,
    ): void {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))->willReturn(true);
        $parent->getOwner()->willReturn($user);
        $definition->getOwner()->willReturn($user);
        $definition->setSlug(null)->willReturn($definition);
        $definition->setData(self::EMPTY_JSON_OBJECT_AS_STRING)->willReturn($definition);
        $definition->setParent($parent)->willReturn($definition);
        $this->definitionToArrayResult($definition, $parent, $user);

        $definitionRepository->read('definition-id')->willReturn($definition);
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'))->willReturn(0);

        $definitionRepository->update($definition)->shouldBeCalled();

        $this->updateDefinition(
            $user->getWrappedObject(),
            'definition-id',
            [
                DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING
            ]
        );
    }

    public function it_updates_a_definition_all_possible_values(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        Definition $definition,
        Entity $parent,
        Entity $newParent,
    ): void {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))->willReturn(true);

        $parent->getOwner()->willReturn($user);
        $newParent->getOwner()->willReturn($user);

        $definition->getOwner()->willReturn($user);
        $definition->setSlug('slug')->willReturn($definition);
        $definition->setData(self::EMPTY_JSON_OBJECT_AS_STRING)->willReturn($definition);
        $definition->setParent($newParent)->willReturn($definition);
        $this->definitionToArrayResult($definition, $parent, $user);

        $definitionRepository->read('definition-id')->willReturn($definition);
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'))->willReturn(0);
        $definitionRepository->update($definition)->shouldBeCalled();

        $this->updateDefinition(
            $user->getWrappedObject(),
            'definition-id',
            [
                DefinitionInputField::SLUG->value => 'slug',
                DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
                DefinitionInputField::PARENT->value => $newParent->getWrappedObject(),
            ]
        );
    }

    public function it_throws_exception_when_to_update_array_keys_are_invalid(
        User $user,
    ): void {
        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('updateDefinition', [
                $user->getWrappedObject(),
                'definition-id',
                [
                    'invalid-key' => 'value'
                ]
            ]);
    }

    public function it_throws_exception_during_update_when_user_is_not_the_owner_of_the_definition(
        DefinitionRepository $definitionRepository,
        Definition $definition,
        User $user,
        User $otherUser,
    ): void {
        $definition->getOwner()->willReturn($user);
        $definitionRepository->read('definition-id')->willReturn($definition);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('updateDefinition', [
                $otherUser->getWrappedObject(),
                'definition-id',
                [
                    DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING
                ]
            ]);
    }

    public function it_throws_exception_during_definition_data_update_when_definition_is_used_by_entities(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        User $user,
        JsonSchemaValidator $jsonSchemaValidator,
        Definition $definition,
        Entity $parent,
    ): void {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))->willReturn(true);
        $jsonSchemaValidator->isValid(new Json('{"foo": "bar"}'))->willReturn(true);

        $parent->getOwner()->willReturn($user);

        $definition->getId()->willReturn('definition-id');
        $definition->getOwner()->willReturn($user);
        $this->definitionToArrayResult($definition, $parent, $user);

        $definitionRepository->read('definition-id')->willReturn($definition);
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'))->willReturn(1);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('updateDefinition', [
                $user,
                'definition-id',
                [
                    DefinitionInputField::DATA->value => '{"foo": "bar"}',
                ]
            ]);
    }

    public function it_removes_a_definition(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        User $user,
        Definition $definition,
        Entity $parent,
    ): void {
        $definition->getId()->willReturn('definition-id');
        $definition->getOwner()->willReturn($user);
        $definition->getParent()->willReturn($parent);

        $definitionRepository->read('definition-id')->willReturn($definition);
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'))->willReturn(0);
        $definitionRepository->delete($definition)->shouldBeCalled();

        $this->removeDefinition($user, 'definition-id');
    }

    public function it_cannot_removes_a_root_definition(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        User $user,
        Definition $definition,
    ): void {
        $definition->getId()->willReturn('definition-id');
        $definition->getOwner()->willReturn($user);
        $definition->getParent()->willReturn(null);

        $definitionRepository->read('definition-id')->willReturn($definition);
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'))->willReturn(0);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('removeDefinition', [
                $user,
                'definition-id'
            ]);
    }

    public function it_throws_exception_during_remove_when_user_is_not_the_owner_of_the_definition(
        DefinitionRepository $definitionRepository,
        User $user,
        User $otherUser,
        Definition $definition,
        Entity $parent,
    ) {
        $definition->getOwner()->willReturn($otherUser);
        $definition->getParent()->willReturn($parent);

        $definitionRepository->read('definition-id')->willReturn($definition);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('removeDefinition', [
                $user,
                'definition-id'
            ]);
    }

    public function it_throws_exception_during_remove_when_definition_is_used_by_entities(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        User $user,
        Definition $definition,
        Entity $parent,
    ) {
        $definition->getId()->willReturn('definition-id');
        $definition->getOwner()->willReturn($user);
        $definition->getParent()->willReturn($parent);

        $definitionRepository->read('definition-id')->willReturn($definition);
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'))->willReturn(1);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('removeDefinition', [
                $user,
                'definition-id'
            ]);
    }

    private function definitionToArrayResult(
        Definition $definition,
        Entity $parent,
        User $user,
    ): void {
        $definition->toArray()->willReturn([
          DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
          DefinitionInputField::PARENT->value => $parent,
          DefinitionInputField::OWNER->value => $user,
        ]);
    }
}
