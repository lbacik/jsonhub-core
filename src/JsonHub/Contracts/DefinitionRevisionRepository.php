<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

interface DefinitionRevisionRepository
{
    public function current(Definition $definition): DefinitionRevision;
    public function byId(Definition $definition, string $revisionId): DefinitionRevision | null;
}
