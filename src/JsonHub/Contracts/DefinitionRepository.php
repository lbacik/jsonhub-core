<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\FilterCriteria;
use JsonHub\Core\ValuesFactory\Definition as DefinitionValues;

interface DefinitionRepository
{
    public function create(DefinitionValues $values): Definition;
    public function read(string $definitionId): Definition;
    public function readAll(FilterCriteria $criteria): array;
    public function update(Definition $definition): void;
    public function delete(Definition $definition): void;
    public function countEntitiesUsingDefinition(Definition $definition): int;
}
