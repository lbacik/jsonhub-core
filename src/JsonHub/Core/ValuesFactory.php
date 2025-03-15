<?php

declare(strict_types=1);

namespace JsonHub\Core;

use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Contracts\JsonValidator;
use JsonHub\Core\Exceptions\CreateDefinitionException;
use JsonHub\Core\Types\DefinitionInputField;
use JsonHub\Core\Types\Definition;
use JsonHub\Core\Types\EntityInputField;
use JsonHub\Core\Types\Entity;
use JsonHub\Core\Types\Json;
use JsonHub\Core\Types\JsonSchema;
use JsonHub\Core\Types\Slug;

readonly class ValuesFactory
{
    public function __construct(
        private JsonValidator $jsonValidator,
        private JsonSchemaValidator $jsonSchemaValidator
    ) {
    }

    public function createDefinition(array $input): Definition
    {
        if (
            empty($input[DefinitionInputField::DATA->value])
            || empty($input[DefinitionInputField::OWNER->value])
            || empty($input[DefinitionInputField::PARENT->value])
        ) {
            throw CreateDefinitionException::notEnoughData();
        }

        return new Definition(
            slug: new Slug($input[DefinitionInputField::SLUG->value] ?? null),
            data: new JsonSchema(
                new Json($input[DefinitionInputField::DATA->value]),
                $this->jsonSchemaValidator
            ),
            parent: $input[DefinitionInputField::PARENT->value],
            owner: $input[DefinitionInputField::OWNER->value],
        );
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

    public function createJsonSchema(string $data): JsonSchema
    {
        return new JsonSchema(new Json($data), $this->jsonSchemaValidator);
    }
}
