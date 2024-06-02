<?php

declare(strict_types=1);

namespace JsonHub\Core;

use JsonHub\Contracts\Definition;
use JsonHub\Contracts\DefinitionRepository;
use JsonHub\Contracts\EntityRepository;
use JsonHub\Contracts\User;
use JsonHub\Core\ValuesFactory\DefinitionInputField;
use JsonHub\Core\ValuesFactory\Definition as DefinitionValues;
use JsonHub\Core\ValuesFactory\Slug;

class DefinitionRegistry
{
    public function __construct(
        private readonly DefinitionRepository $definitionRepository,
        private readonly EntityRepository $entityRepository,
        private readonly ValuesFactory $valuesFactory,
    ) {
    }

    public function getDefinition(string $definitionId): Definition|null
    {
        return $this->definitionRepository->read($definitionId);
    }

    public function getDefinitions(FilterCriteria $criteria): array
    {
        return $this->definitionRepository->readAll($criteria);
    }

    public function countDefinitions(FilterCriteria $criteria): int
    {
        return $this->definitionRepository->count($criteria);
    }

    public function addDefinition(DefinitionValues $definitionValues): Definition
    {
        return $this->definitionRepository->create($definitionValues);
    }

    public function updateDefinition(User $user, string $definitionId, array $toUpdate): Definition
    {
        $this->validateDefinitionToUpdateArrayKeys($toUpdate);

        $definition = $this->definitionRepository->read($definitionId);

        if ($definition->getOwner()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('User is not the owner of the definition');
        }

        $values = $this->valuesFactory->createDefinition($definition->toArray());
        $valuesToUpdate = $this->mapDefinitionValues($toUpdate);
        $updatedValues = $values->set(...$valuesToUpdate);

        if (
            $values->data->isEqual($updatedValues->data) === false
            && $this->entityRepository->count(new FilterCriteria(definition: $definition->getId())) > 0
        ) {
            throw new \InvalidArgumentException('Cant update schema - definition is used by entities');
        }

        $definition
            ->setSlug($updatedValues->slug->value)
            ->setData($updatedValues->data->getValueAsString())
            ->setParent($updatedValues->parent);

        $this->definitionRepository->update($definition);

        return $definition;
    }

    public function removeDefinition(User $user, string $definitionId): void
    {
        $definition = $this->definitionRepository->read($definitionId);

        if ($definition->getParent() === null) {
            throw new \InvalidArgumentException('Root definitions cannot be removed');
        }

        if ($definition->getOwner()->getId() !== $user->getId()) {
            throw new \InvalidArgumentException('User is not the owner of the definition');
        }

        if ($this->entityRepository->count(new FilterCriteria(definition: $definition->getId())) > 0) {
            throw new \InvalidArgumentException('Definition is used by entities');
        }

        $this->definitionRepository->delete($definition);
    }

    private function validateDefinitionToUpdateArrayKeys(array $toUpdate): void
    {
        foreach (array_keys($toUpdate) as $key) {
            if (
                !in_array($key, [
                DefinitionInputField::SLUG->value,
                DefinitionInputField::DATA->value,
                DefinitionInputField::PARENT->value,
                ])
            ) {
                throw new \InvalidArgumentException("Invalid field: $key");
            }
        }
    }

    private function mapDefinitionValues(array $toUpdate): array
    {
        return array_reduce(
            array_keys($toUpdate),
            function ($carry, $key) use ($toUpdate) {
                $value = $toUpdate[$key];
                return match ($key) {
                    DefinitionInputField::SLUG->value => array_merge(
                        $carry,
                        [DefinitionInputField::SLUG->value => new Slug($value)]
                    ),
                    DefinitionInputField::DATA->value => array_merge(
                        $carry,
                        [DefinitionInputField::DATA->value => $this->valuesFactory->createJsonSchema($value)]
                    ),
                    default => array_merge($carry, [$key => $value]),
            //                    default => $carry,
                };
            },
            []
        );
    }
}
