<?php

declare(strict_types=1);

namespace JsonHub\Core\ValuesFactory;

use JsonHub\Contracts\Definition;
use JsonHub\Contracts\Entity as EntityRecord;
use JsonHub\Contracts\JsonValidator;
use JsonHub\Contracts\User;
use JsonHub\Core\Exceptions\CreateEntityException;
use Sushi\ValueObject;
use Sushi\ValueObject\Invariant;

class Entity extends ValueObject
{
    public function __construct(
        public readonly Slug $slug,
        public readonly Json $data,
        public readonly Definition $definition,
        public readonly EntityRecord|null $parent,
        public readonly User $owner,
        public readonly bool $private,
        private readonly JsonValidator $jsonValidator,
    ) {
        parent::__construct();
    }

    #[Invariant]
    public function checkIfParentOwnerIsSameAsEntityOwner(): void
    {
        if ($this->parent !== null && $this->parent->getOwner()->getId() !== $this->owner->getId()) {
            throw CreateEntityException::parentOwnerMismatch();
        }
    }

    #[Invariant]
    public function validateDataBySchema(): void
    {
        if ($this->jsonValidator->isValid($this->data, new Json($this->definition->getData())) === false) {
            throw CreateEntityException::notValidData($this->jsonValidator->getError());
        }
    }
}
