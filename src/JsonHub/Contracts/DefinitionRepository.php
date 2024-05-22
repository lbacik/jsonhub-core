<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\FilterCriteria;
use JsonHub\Core\ValuesFactory\Definition as DefinitionValues;

interface DefinitionRepository
{
    public function create(DefinitionValues $values): Definition;
    public function read(string $definitionId): Definition|null;
    public function readAll(FilterCriteria $criteria): array;
    public function count(FilterCriteria $criteria): int;
    public function update(Definition $definition): void;
    public function delete(Definition $definition): void;
}
