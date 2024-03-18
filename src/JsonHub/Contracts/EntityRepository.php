<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\FilterCriteria;
use JsonHub\Core\ValuesFactory\Entity as EntityValues;

interface EntityRepository
{
    public function create(EntityValues $values): Entity;
    public function read(string $entityId): Entity;
    public function readAll(FilterCriteria $criteria): array;
    public function readAllPrivate(FilterCriteria $criteria, User $user): array;
    public function update(Entity $entity): void;
    public function delete(Entity $entity): void;
    public function countChildren(Entity $entity): int;
}
