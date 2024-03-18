<?php

declare(strict_types=1);

namespace JsonHub\Core\ValuesFactory;

use JsonHub\Contracts\Entity;
use JsonHub\Contracts\User;
use JsonHub\Core\Exceptions\CreateDefinitionException;
use Sushi\ValueObject;
use Sushi\ValueObject\Invariant;

class Definition extends ValueObject
{
    public function __construct(
        public Slug $slug,
        public JsonSchema $data,
        public Entity | null $parent,
        public User $owner,
    ) {
        parent::__construct();
    }

    #[Invariant]
    public function checkIfParentOwnerIsSameAsDefinitionOwner(): void
    {
        if ($this->parent !== null && $this->parent->getOwner() !== $this->owner) {
            throw CreateDefinitionException::parentMismatch();
        }
    }
}
