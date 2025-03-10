<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\FilterCriteria;
use JsonHub\Core\ValuesFactory\Entity as EntityValues;

interface EntityRepository
{
    public function create(EntityValues $values): Entity;
    public function read(string $entityId): Entity|null;
    public function readAll(FilterCriteria $criteria, bool $isSystemQuery = false): array;
    public function count(FilterCriteria $criteria, bool $isSystemQuery = false): int;
    public function update(Entity $entity): void;
    public function delete(Entity $entity): void;
    public function countChildren(Entity $entity): int;
}
