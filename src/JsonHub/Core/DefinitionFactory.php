<?php

declare(strict_types=1);

namespace JsonHub\Core;

use JsonHub\Core\Exceptions\CreateDefinitionException;
use JsonHub\Core\Types\Definition;
use JsonHub\Core\Types\Metadata\DefinitionInputField;
use JsonHub\Core\Types\Slug;

readonly class DefinitionFactory
{
    public function __construct(
        private JsonSchemaFactory $jsonSchemaFactory,
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
            data: $this->jsonSchemaFactory->createJsonSchema($input[DefinitionInputField::DATA->value]),
            parent: $input[DefinitionInputField::PARENT->value],
            owner: $input[DefinitionInputField::OWNER->value],
        );
    }
}
