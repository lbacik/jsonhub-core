<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\FilterCriteria;
use JsonHub\Core\Types\Entity as EntityValues;

interface EntityRepository
{
    public function create(EntityValues $values): Entity;
    public function read(string $entityId): Entity|null;
    /**
     * @param FilterCriteria $criteria filter the entities to read.
     * @param bool $isSystemQuery when true, the query covers active entities of every owner
     * and both visibilities (public and private); logically deleted entities are excluded.
     */
    public function readAll(FilterCriteria $criteria, bool $isSystemQuery = false): array;
    /**
     * @param FilterCriteria $criteria filter the entities to count.
     * @param bool $isSystemQuery when true, the count covers active entities of every owner
     * and both visibilities (public and private); logically deleted entities are excluded.
     */
    public function count(FilterCriteria $criteria, bool $isSystemQuery = false): int;
    public function update(Entity $entity): void;
    public function delete(Entity $entity): void;
    public function countChildren(Entity $entity): int;
}
