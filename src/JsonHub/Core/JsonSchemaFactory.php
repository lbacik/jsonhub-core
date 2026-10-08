<?php

declare(strict_types=1);

namespace JsonHub\Core;

use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Core\Types\Json;
use JsonHub\Core\Types\JsonSchema;

readonly class JsonSchemaFactory
{
    public function __construct(
        private JsonSchemaValidator $jsonSchemaValidator
    ) {
    }

    public function createJsonSchema(string $data): JsonSchema
    {
        return new JsonSchema(new Json($data), $this->jsonSchemaValidator);
    }
}
