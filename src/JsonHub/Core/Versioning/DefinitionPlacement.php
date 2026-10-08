<?php

declare(strict_types=1);

namespace JsonHub\Core\Versioning;

use JsonHub\Contracts\Entity;
use JsonHub\Contracts\User;
use JsonHub\Core\Exceptions\CreateDefinitionException;
use Sushi\ValueObject;
use Sushi\ValueObject\Invariant;

class DefinitionPlacement extends ValueObject
{
    public function __construct(
        public readonly User | null $owner,
        public readonly Entity | null $parent,
    ) {
        parent::__construct();
    }

    #[Invariant]
    public function checkPlacement(): void
    {
        if ($this->owner === null && $this->parent === null) {
            return;
        }

        if ($this->owner === null || $this->parent === null) {
            throw CreateDefinitionException::ownerParentPairing();
        }

        if ($this->parent->getOwner()->getId() !== $this->owner->getId()) {
            throw CreateDefinitionException::parentMismatch();
        }
    }
}
