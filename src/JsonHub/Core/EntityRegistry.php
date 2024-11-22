<?php

declare(strict_types=1);

namespace JsonHub\Core;

use JsonHub\Contracts\Entity;
use JsonHub\Contracts\EntityRepository;
use JsonHub\Contracts\User;
use JsonHub\Core\ValuesFactory\EntityInputField;
use JsonHub\Core\ValuesFactory\Entity as EntityValues;
use JsonHub\Core\ValuesFactory\Json;
use JsonHub\Core\ValuesFactory\Slug;

class EntityRegistry
{
    public function __construct(
        private readonly EntityRepository $entityRepository,
        private readonly ValuesFactory $valuesFactory,
    ) {
    }

    public function getEntity(string $entityId, User|null $authenticatedUser = null): Entity|null
    {
        $entity = $this->entityRepository->read($entityId);

        if (
            $entity
            && $entity->isPrivate()
            && (!$authenticatedUser || $authenticatedUser->getId() !== $entity->getOwner()->getId())
        ) {
            throw new \InvalidArgumentException('Entity is private');
        }

        return $entity;
    }

    public function getEntities(FilterCriteria $criteria, User|null $authenticatedUser = null): array
    {
        $this->validateFilterCriteria($criteria, $authenticatedUser);

        return $this->entityRepository->readAll($criteria);
    }

    public function countEntities(FilterCriteria $criteria, User|null $authenticatedUser = null): int
    {
        $this->validateFilterCriteria($criteria, $authenticatedUser);

        return $this->entityRepository->count($criteria);
    }

    public function addEntity(EntityValues $entityValues): Entity
    {
        return $this->entityRepository->create($entityValues);
    }

    public function updateEntity(User $user, string $entityId, array $toUpdate): Entity
    {
        $this->validateEntityToUpdateArrayKeys($toUpdate);
        $entity = $this->entityRepository->read($entityId);

        if ($entity->getOwner()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('User is not the owner of the entity');
        }

        $values = $this->valuesFactory->createEntity($entity->toArray());
        $valuesToUpdate = $this->mapEntityValues($toUpdate);
        $updatedValues = $values->set(...$valuesToUpdate);

        $entity
            ->setSlug($updatedValues->slug->value)
            ->setData($updatedValues->data->value)
            ->setParent($updatedValues->parent)
            ->setPrivate($updatedValues->private);

        $this->entityRepository->update($entity);

        return $entity;
    }

    public function removeEntity(User $user, string $entityId): void
    {
        $entity = $this->entityRepository->read($entityId);

        if ($entity->getOwner()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('User is not the owner of the entity');
        }

        if ($this->entityRepository->countChildren($entity) > 0) {
            throw new \InvalidArgumentException('Entity has children');
        }

        $this->entityRepository->delete($entity);
    }

    private function validateFilterCriteria(FilterCriteria $criteria, User|null $authenticatedUser): void
    {
        if ($criteria->private && !$authenticatedUser) {
            throw new \InvalidArgumentException('You have to be authenticated to get private entities');
        }

        if ($criteria->owner && $criteria->owner !== $authenticatedUser?->getId()) {
            throw new \InvalidArgumentException('You cannot filter entities by other users');
        }
    }

    private function validateEntityToUpdateArrayKeys(array $toUpdate): void
    {
        foreach (array_keys($toUpdate) as $key) {
            if (
                !in_array($key, [
                EntityInputField::SLUG->value,
                EntityInputField::DATA->value,
                EntityInputField::PARENT->value,
                EntityInputField::PRIVATE->value,
                ])
            ) {
                throw new \InvalidArgumentException("Invalid field: $key");
            }
        }
    }

    private function mapEntityValues(array $toUpdate): array
    {
        return array_reduce(
            array_keys($toUpdate),
            function (array $carry, string $key) use ($toUpdate) {
                $value = $toUpdate[$key];

                return match ($key) {
                    EntityInputField::SLUG->value => array_merge(
                        $carry,
                        [EntityInputField::SLUG->value => new Slug($value)]
                    ),
                    EntityInputField::DATA->value => array_merge(
                        $carry,
                        [EntityInputField::DATA->value => new Json($value)]
                    ),
                    default => array_merge($carry, [$key => $value]),
                };
            },
            []
        );
    }
}
