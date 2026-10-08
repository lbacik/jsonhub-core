<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

interface PinnedEntity extends Entity
{
    public function getDefinitionRevision(): DefinitionRevision;
}
