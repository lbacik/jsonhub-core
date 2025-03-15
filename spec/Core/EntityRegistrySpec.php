<?php

declare(strict_types=1);

namespace spec\JsonHub\Core;

use JsonHub\Contracts\Definition;
use JsonHub\Contracts\Entity;
use JsonHub\Contracts\EntityRepository;
use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Contracts\JsonValidator;
use JsonHub\Contracts\User;
use JsonHub\Core\EntityRegistry;
use JsonHub\Core\FilterCriteria;
use JsonHub\Core\Types;
use JsonHub\Core\Types\EntityInputField;
use JsonHub\Core\Types\Json;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class EntityRegistrySpec extends ObjectBehavior
{
    private const EMPTY_JSON_OBJECT_AS_STRING = '{}';

    private ValuesFactory $valuesFactory;

    public function let(
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

        $this->beConstructedWith($entityRepository, $this->valuesFactory);

        $user->getId()->willReturn('user-id');
        $otherUser->getId()->willReturn('other-user-id');
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(EntityRegistry::class);
    }

    public function it_returns_an_entity(
        EntityRepository $entityRepository,
        Entity $entity,
    ): void {
        $entity->isPrivate()->willReturn(false);

        $entityRepository->read('entity-id')->willReturn($entity);
        $this->getEntity('entity-id')->shouldBeAnInstanceOf(Entity::class);
    }

    public function it_throws_an_exception_when_getting_a_private_entity_without_user(
        EntityRepository $entityRepository,
        Entity $entity,
    ): void {
        $entity->isPrivate()->willReturn(true);

        $entityRepository->read('entity-id')->willReturn($entity);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('getEntity', ['entity-id']);
    }

    public function it_throws_an_exception_when_getting_a_private_entity_of_other_user(
        EntityRepository $entityRepository,
        User $user,
        User $otherUser,
        Entity $entity,
    ): void {
        $entity->isPrivate()->willReturn(true);
        $entity->getOwner()->willReturn($user);

        $entityRepository->read('entity-id')->willReturn($entity);
        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('getEntity', ['entity-id', $otherUser]);
    }

    public function it_returns_private_entity_of_the_user(
        EntityRepository $entityRepository,
        User $user,
        Entity $entity,
    ): void {
        $entity->isPrivate()->willReturn(true);
        $entity->getOwner()->willReturn($user);

        $entityRepository->read('entity-id')->willReturn($entity);

        $this->getEntity('entity-id', $user)
            ->shouldBeAnInstanceOf(Entity::class);
    }

    public function it_returns_entities(EntityRepository $entityRepository): void
    {
        $criteria = new FilterCriteria();

        $entityRepository->readAll($criteria, false)->shouldBeCalled();
        $this->getEntities($criteria)->shouldBeArray();
    }

    public function it_requires_user_to_get_private_entities(): void
    {
        $criteria = new FilterCriteria();
        $criteria->private = true;

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('getEntities', [$criteria]);
    }

    public function it_counts_entities(EntityRepository $entityRepository): void
    {
        $criteria = new FilterCriteria();

        $entityRepository->count($criteria, false)->willReturn(3);
        $this->countEntities($criteria)->shouldReturn(3);
    }

    public function it_returns_all_entities_for_the_system_user(
        EntityRepository $entityRepository,
        Entity $entity,
        User $user,
    ): void {
        $criteria = new FilterCriteria();
        $user->isSystemUser()->willReturn(true);
        $entityRepository->readAll($criteria, true)->willReturn([$entity])->shouldBeCalledOnce();

        $this->getEntities($criteria, $user)->shouldReturn([$entity]);
    }

    public function it_rejects_owner_filters_without_authentication(EntityRepository $entityRepository): void
    {
        $criteria = new FilterCriteria(owner: 'user-id');
        $entityRepository->readAll(Argument::cetera())->shouldNotBeCalled();
        $entityRepository->count(Argument::cetera())->shouldNotBeCalled();

        $this->shouldThrow(\InvalidArgumentException::class)->during('getEntities', [$criteria]);
        $this->shouldThrow(\InvalidArgumentException::class)->during('countEntities', [$criteria]);
    }

    public function it_counts_all_entities_for_the_system_user(
        EntityRepository $entityRepository,
        User $user,
    ): void {
        $criteria = new FilterCriteria();
        $user->isSystemUser()->willReturn(true);

        $entityRepository->count($criteria, true)->willReturn(5);
        $this->countEntities($criteria, $user)->shouldReturn(5);
    }

    public function it_does_not_allow_filtering_entities_by_other_users(User $user): void
    {
        $criteria = new FilterCriteria(owner: 'other-user-id');

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('getEntities', [$criteria, $user]);
        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('countEntities', [$criteria, $user]);
    }

    public function it_creates_an_entity(
        JsonValidator $jsonValidator,
        EntityRepository $entityRepository,
        Entity $entity,
        Definition $definition,
        User $user,
    ): void {
        $jsonValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING), Argument::type(Json::class))
            ->willReturn(true);

        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);

        $entityValues = $this->valuesFactory->createEntity([
            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            EntityInputField::DEFINITION->value => $definition->getWrappedObject(),
            EntityInputField::OWNER->value => $user->getWrappedObject(),
        ]);

        $entityRepository->create($entityValues)->willReturn($entity);
        $this->addEntity($entityValues)->shouldBeAnInstanceOf(Entity::class);
    }

    public function it_creates_an_entity_with_all_possible_values(
        JsonValidator $jsonValidator,
        EntityRepository $entityRepository,
        Entity $entity,
        Entity $parent,
        Definition $definition,
        User $user,
    ): void {
        $jsonValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING), Argument::type(Json::class))
            ->willReturn(true);

        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);
        $parent->getOwner()->willReturn($user);

        $entityValues = $this->valuesFactory->createEntity([
            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            EntityInputField::DEFINITION->value => $definition->getWrappedObject(),
            EntityInputField::OWNER->value => $user->getWrappedObject(),
            EntityInputField::PARENT->value => $parent->getWrappedObject(),
            EntityInputField::PRIVATE->value => false,
            EntityInputField::SLUG->value => 'slug',
        ]);

        $entityRepository->create($entityValues)->willReturn($entity);

        $this->addEntity($entityValues)->shouldBeAnInstanceOf(Entity::class);
    }

    public function it_updates_an_entity(
        JsonValidator $jsonValidator,
        EntityRepository $entityRepository,
        Entity $entity,
        Definition $definition,
        User $user,
    ): void {
        $jsonValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING), Argument::type(Json::class))
            ->willReturn(true);

        $entity->getOwner()->willReturn($user);
        $entity->toArray()->willReturn([
            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            EntityInputField::DEFINITION->value => $definition->getWrappedObject(),
            EntityInputField::OWNER->value => $user->getWrappedObject(),
        ]);

        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);

        $entity->setSlug(null)->willReturn($entity);
        $entity->setData(self::EMPTY_JSON_OBJECT_AS_STRING)->willReturn($entity);
        $entity->setParent(null)->willReturn($entity);
        $entity->setPrivate(false)->willReturn($entity);

        $entityRepository->read('entity-id')->willReturn($entity);
        $entityRepository->update($entity)->shouldBeCalled();

        $this->updateEntity(
            $user,
            'entity-id',
            [
                EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            ]
        )->shouldBeAnInstanceOf(Entity::class);
    }

    public function it_updates_an_entity_with_all_possible_values(
        JsonValidator $jsonValidator,
        EntityRepository $entityRepository,
        Entity $entity,
        Entity $parent,
        Definition $definition,
        User $user,
    ): void {
        $jsonValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING), Argument::type(Json::class))
            ->willReturn(true);
        $jsonValidator->isValid(new Json('{"updated":true}'), Argument::type(Json::class))->willReturn(true);

        $entity->getOwner()->willReturn($user);
        $entity->toArray()->willReturn([
            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            EntityInputField::DEFINITION->value => $definition->getWrappedObject(),
            EntityInputField::OWNER->value => $user->getWrappedObject(),
        ]);

        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);
        $parent->getOwner()->willReturn($user);

        $entity->setSlug('slug')->willReturn($entity)->shouldBeCalledTimes(2);
        $entity->setData('{"updated":true}')->willReturn($entity)->shouldBeCalledTimes(2);
        $entity->setParent($parent)->willReturn($entity)->shouldBeCalledTimes(2);
        $entity->setPrivate(true)->willReturn($entity)->shouldBeCalledTimes(2);

        $entityRepository->read('entity-id')->willReturn($entity);
        $entityRepository->update($entity)->shouldBeCalledTimes(2);

        $updates = [
            EntityInputField::SLUG->value => 'slug',
            EntityInputField::DATA->value => '{"updated":true}',
            EntityInputField::PARENT->value => $parent,
            EntityInputField::PRIVATE->value => true,
        ];
        foreach ([$updates, array_reverse($updates, true)] as $toUpdate) {
            $this->updateEntity($user, 'entity-id', $toUpdate)->shouldReturn($entity);
        }
    }

    public function it_throws_exception_when_updating_entity_with_invalid_field(
        User $user,
        Definition $definition,
    ): void {

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('updateEntity', [
                $user,
                'entity-id',
                [
                    EntityInputField::DEFINITION->value => $definition
                ]
            ]);
    }

    public function it_throws_exception_when_updating_entity_of_other_user(
        EntityRepository $entityRepository,
        User $user,
        User $otherUser,
        Entity $entity,
    ): void {
        $entity->getOwner()->willReturn($user);

        $entityRepository->read('entity-id')->willReturn($entity);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('updateEntity', [
                $otherUser,
                'entity-id',
                [
                    EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
                ]
            ]);
    }

//    public function it_throws_exception_when_updating_entity_with_invalid_json(
//        JsonValidator $jsonValidator,
//        EntityRepository $entityRepository,
//        Entity $entity,
//        Definition $definition,
//        User $user,
//    ): void {
//        $jsonValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING), self::EMPTY_JSON_OBJECT_AS_STRING)
//            ->willReturn(true);
//
//        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);
//
//        $entity->getOwner()->willReturn($user);
//        $entity->toArray()->willReturn([
//            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
//            EntityInputField::DEFINITION->value => $definition->getWrappedObject(),
//            EntityInputField::OWNER->value => $user->getWrappedObject(),
//        ]);
//
//        $entityRepository->read('entity-id')->willReturn($entity);
//
//        $this->shouldThrow(\JsonException::class)
//            ->during('updateEntity', [
//                $user,
//                'entity-id',
//                [
//                    EntityInputField::DATA->value => "'invalid-json'",
//                ]
//            ]);
//    }

//    public function it_throws_exception_when_updating_entity_with_not_correct_data(
//        JsonValidator $jsonValidator,
//        EntityRepository $entityRepository,
//        Entity $entity,
//        Definition $definition,
//        User $user,
//    ): void {
//        $jsonValidator->validate(self::EMPTY_JSON_OBJECT_AS_STRING, self::EMPTY_JSON_OBJECT_AS_STRING)
//            ->willReturn(false);
//
//        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);
//
//        $entity->getOwner()->willReturn($user);
//        $entity->toArray()->willReturn([
//            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
//            EntityInputField::DEFINITION->value => $definition->getWrappedObject(),
//            EntityInputField::OWNER->value => $user->getWrappedObject(),
//        ]);
//
//        $entityRepository->read('entity-id')->willReturn($entity);
//
//        $this->shouldThrow(\InvalidArgumentException::class)
//            ->during('updateEntity', [
//                $user,
//                'entity-id',
//                [
//                    EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
//                ]
//            ]);
//    }

//    public function it_throws_exception_when_updating_parent_and_its_owner_doesnt_match_entity_owner(
//        JsonValidator $jsonValidator,
//        EntityRepository $entityRepository,
//        Entity $entity,
//        Entity $parent,
//        Definition $definition,
//        User $user,
//        User $otherUser,
//    ): void {
//        $jsonValidator->validate(self::EMPTY_JSON_OBJECT_AS_STRING, self::EMPTY_JSON_OBJECT_AS_STRING)
//            ->willReturn(true);
//
//        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);
//        $parent->getOwner()->willReturn($otherUser);
//
//        $entity->getOwner()->willReturn($user);
//        $entity->toArray()->willReturn([
//            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
//            EntityInputField::DEFINITION->value => $definition->getWrappedObject(),
//            EntityInputField::OWNER->value => $user->getWrappedObject(),
//        ]);
//
//        $entityRepository->read('entity-id')->willReturn($entity);
//
//        $this->shouldThrow(\InvalidArgumentException::class)
//            ->during('updateEntity', [
//                $user,
//                'entity-id',
//                [
//                    EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
//                    EntityInputField::PARENT->value => $parent,
//                ]
//            ]);
//    }

    public function it_can_remove_entity(
        EntityRepository $entityRepository,
        Entity $entity,
        User $user,
    ): void {
        $entity->getOwner()->willReturn($user);
        $entityRepository->read('entity-id')->willReturn($entity);

        $entityRepository->countChildren($entity)->willReturn(0);
        $entityRepository->delete($entity)->shouldBeCalled();

        $this->removeEntity($user, 'entity-id');
    }

    public function it_throws_exception_when_removing_entity_of_other_user(
        EntityRepository $entityRepository,
        Entity $entity,
        User $user,
        User $otherUser,
    ): void {
        $entity->getOwner()->willReturn($user);
        $entityRepository->read('entity-id')->willReturn($entity);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('removeEntity', [$otherUser, 'entity-id']);
    }

    public function it_throws_exception_when_removing_entity_with_children(
        EntityRepository $entityRepository,
        Entity $entity,
        User $user,
    ): void {
        $entity->getOwner()->willReturn($user);
        $entityRepository->read('entity-id')->willReturn($entity);

        $entityRepository->countChildren($entity)->willReturn(1);

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('removeEntity', [$user, 'entity-id']);
    }
}
