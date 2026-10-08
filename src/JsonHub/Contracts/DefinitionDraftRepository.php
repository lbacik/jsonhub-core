<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

interface DefinitionDraftRepository
{
    public function forDefinition(Definition $definition): DefinitionDraft;
}
