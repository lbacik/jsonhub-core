<?php

declare(strict_types=1);

namespace spec\JsonHub\Core;

use JsonHub\Contracts\Definition;
use JsonHub\Contracts\DefinitionRepository;
use JsonHub\Contracts\Entity;
use JsonHub\Contracts\EntityRepository;
use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Contracts\User;
use JsonHub\Core\DefinitionFactory;
use JsonHub\Core\DefinitionRegistry;
use JsonHub\Core\FilterCriteria;
use JsonHub\Core\JsonSchemaFactory;
use JsonHub\Core\Types\Json;
use JsonHub\Core\Types\Metadata\DefinitionInputField;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class DefinitionRegistrySpec extends ObjectBehavior
{
    private const EMPTY_JSON_OBJECT_AS_STRING = '{}';

    private DefinitionFactory $definitionFactory;

    public function let(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        User $otherUser,
    ): void {
        $jsonSchemaFactory = new JsonSchemaFactory($jsonSchemaValidator->getWrappedObject());
        $this->definitionFactory = new DefinitionFactory($jsonSchemaFactory);

        $this->beConstructedWith(
            $definitionRepository,
            $entityRepository,
            $this->definitionFactory,
            $jsonSchemaFactory,
        );

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

    public function it_counts_definitions(DefinitionRepository $definitionRepository): void
    {
        $criteria = new FilterCriteria();

        $definitionRepository->count($criteria)->willReturn(3);
        $this->countDefinitions($criteria)->shouldReturn(3);
    }

    public function it_returns_definitions_of_the_authenticated_user(
        DefinitionRepository $definitionRepository,
        User $user,
    ): void {
        $criteria = new FilterCriteria(owner: 'user-id');

        $definitionRepository->readAll($criteria)->shouldBeCalled();
        $this->getDefinitions($criteria, $user);
    }

    public function it_does_not_allow_filtering_definitions_by_other_users(User $user): void
    {
        $criteria = new FilterCriteria(owner: 'other-user-id');

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('getDefinitions', [$criteria, $user]);
        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('countDefinitions', [$criteria, $user]);
    }

    public function it_adds_a_definition(
        DefinitionRepository $definitionRepository,
        JsonSchemaValidator $jsonSchemaValidator,
        User $user,
        Entity $entity,
    ): void {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))->willReturn(true);
        $entity->getOwner()->willReturn($user);

        $definitionValues = $this->definitionFactory->createDefinition([
            DefinitionInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            DefinitionInputField::OWNER->value => $user->getWrappedObject(),
            DefinitionInputField::PARENT->value => $entity->getWrappedObject(),
        ]);

        $definitionRepository->create($definitionValues)->shouldBeCalled();
        $this->addDefinition($definitionValues)->shouldBeAnInstanceOf(Definition::class);
    }

    public function it_rejects_owner_filters_without_authentication(DefinitionRepository $definitionRepository): void
    {
        $criteria = new FilterCriteria(owner: 'user-id');
        $definitionRepository->readAll(Argument::cetera())->shouldNotBeCalled();
        $definitionRepository->count(Argument::cetera())->shouldNotBeCalled();

        $this->shouldThrow(\InvalidArgumentException::class)->during('getDefinitions', [$criteria]);
        $this->shouldThrow(\InvalidArgumentException::class)->during('countDefinitions', [$criteria]);
    }

    public function it_updates_all_values_when_the_changed_schema_is_unused(
        DefinitionRepository $definitionRepository,
        EntityRepository $entityRepository,
        JsonSchemaValidator $jsonSchemaValidator,
        Definition $definition,
        Entity $parent,
        User $user,
    ): void {
        $jsonSchemaValidator->isValid(Argument::type(Json::class))->willReturn(true);
        $parent->getOwner()->willReturn($user);
        $definition->getId()->willReturn('definition-id');
        $definition->getOwner()->willReturn($user);
        $this->definitionToArrayResult($definition, $parent, $user);
        $definitionRepository->read('definition-id')->willReturn($definition);
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)
            ->willReturn(0)->shouldBeCalledOnce();

        $definition->setSlug('updated')->willReturn($definition)->shouldBeCalledOnce();
        $definition->setData('{"type":"object"}')->willReturn($definition)->shouldBeCalledOnce();
        $definition->setParent($parent)->willReturn($definition)->shouldBeCalledOnce();
        $definitionRepository->update($definition)->shouldBeCalledOnce();

        $this->updateDefinition($user, 'definition-id', [
            DefinitionInputField::DATA->value => '{"type":"object"}',
            DefinitionInputField::SLUG->value => 'updated',
            DefinitionInputField::PARENT->value => $parent,
        ])->shouldReturn($definition);
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
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(0);

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
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(0);
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
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(1);

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
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(0);
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
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(0);

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
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(1);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('removeDefinition', [
                $user,
                'definition-id'
            ]);
    }

    public function it_rejects_remove_when_the_only_active_entity_is_private(
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
        // A non-system query sees only public entities, so it counts 0 here;
        // the system query covers both visibilities and counts the private entity.
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), false)->shouldNotBeCalled();
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(1);

        $this->shouldThrow(new \InvalidArgumentException('Definition is used by entities'))
            ->during('removeDefinition', [
                $user,
                'definition-id'
            ]);
    }

    public function it_rejects_remove_when_the_only_active_entity_belongs_to_another_owner(
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
        // A non-system query is owner-scoped, so it counts 0 here;
        // the system query covers every owner and counts the foreign entity.
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), false)->shouldNotBeCalled();
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(1);

        $this->shouldThrow(new \InvalidArgumentException('Definition is used by entities'))
            ->during('removeDefinition', [
                $user,
                'definition-id'
            ]);
    }

    public function it_rejects_schema_update_when_the_only_active_entity_is_private(
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
        // A non-system query sees only public entities, so it counts 0 here;
        // the system query covers both visibilities and counts the private entity.
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), false)->shouldNotBeCalled();
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(1);

        $this->shouldThrow(new \InvalidArgumentException('Cant update schema - definition is used by entities'))
            ->during('updateDefinition', [
                $user,
                'definition-id',
                [
                    DefinitionInputField::DATA->value => '{"foo": "bar"}',
                ]
            ]);
    }

    public function it_rejects_schema_update_when_the_only_active_entity_belongs_to_another_owner(
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
        // A non-system query is owner-scoped, so it counts 0 here;
        // the system query covers every owner and counts the foreign entity.
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), false)->shouldNotBeCalled();
        $entityRepository->count(new FilterCriteria(definition: 'definition-id'), true)->willReturn(1);

        $this->shouldThrow(new \InvalidArgumentException('Cant update schema - definition is used by entities'))
            ->during('updateDefinition', [
                $user,
                'definition-id',
                [
                    DefinitionInputField::DATA->value => '{"foo": "bar"}',
                ]
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
