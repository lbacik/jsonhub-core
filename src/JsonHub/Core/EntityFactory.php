<?php

declare(strict_types=1);

namespace JsonHub\Core;

use JsonHub\Contracts\JsonValidator;
use JsonHub\Core\Types\Entity;
use JsonHub\Core\Types\Json;
use JsonHub\Core\Types\Metadata\EntityInputField;
use JsonHub\Core\Types\Slug;

readonly class EntityFactory
{
    public function __construct(
        private JsonValidator $jsonValidator,
    ) {
    }

    public function createEntity(array $input): Entity
    {
        if (
            empty($input[EntityInputField::DEFINITION->value])
            || empty($input[EntityInputField::OWNER->value])
        ) {
            throw new \InvalidArgumentException('Fields Definition and User are required');
        }

        return new Entity(
            slug: new Slug($input[EntityInputField::SLUG->value] ?? null),
            data: new Json($input[EntityInputField::DATA->value]),
            definition: $input[EntityInputField::DEFINITION->value],
            parent: $input[EntityInputField::PARENT->value] ?? null,
            owner: $input[EntityInputField::OWNER->value],
            private: $input[EntityInputField::PRIVATE->value] ?? false,
            jsonValidator: $this->jsonValidator,
        );
    }
}
