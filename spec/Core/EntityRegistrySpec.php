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
use JsonHub\Core\ValuesFactory;
use JsonHub\Core\ValuesFactory\EntityInputField;
use JsonHub\Core\ValuesFactory\Json;
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

        $entityRepository->readAll($criteria)->shouldBeCalled();
        $this->getEntities($criteria)->shouldBeArray();
    }

    public function it_requires_user_to_get_private_entities(): void
    {
        $criteria = new FilterCriteria();
        $criteria->private = true;

        $this->shouldThrow(\InvalidArgumentException::class)
            ->during('getEntities', [$criteria]);
    }

    public function it_requires_user_to_be_the_owner_of_the_private_entities(
        EntityRepository $entityRepository,
        User $user,
    ): void {
        $criteria = new FilterCriteria(
            private: true,
        );

        $entityRepository->readAllPrivate($criteria, $user)->willReturn([]);

        $this->getEntities($criteria, $user)->shouldBeArray();
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

        $entity->getOwner()->willReturn($user);
        $entity->toArray()->willReturn([
            EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
            EntityInputField::DEFINITION->value => $definition->getWrappedObject(),
            EntityInputField::OWNER->value => $user->getWrappedObject(),
        ]);

        $definition->getData()->willReturn(self::EMPTY_JSON_OBJECT_AS_STRING);
        $parent->getOwner()->willReturn($user);

        $entity->setSlug('slug')->willReturn($entity);
        $entity->setData(self::EMPTY_JSON_OBJECT_AS_STRING)->willReturn($entity);
        $entity->setParent($parent)->willReturn($entity);
        $entity->setPrivate(false)->willReturn($entity);

        $entityRepository->read('entity-id')->willReturn($entity);
        $entityRepository->update($entity)->shouldBeCalled();

        $this->updateEntity(
            $user,
            'entity-id',
            [
                EntityInputField::DATA->value => self::EMPTY_JSON_OBJECT_AS_STRING,
                EntityInputField::PARENT->value => $parent,
                EntityInputField::PRIVATE->value => false,
                EntityInputField::SLUG->value => 'slug',
            ]
        )->shouldBeAnInstanceOf(Entity::class);
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
