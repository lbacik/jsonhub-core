<?php

declare(strict_types=1);

namespace JsonHub\Core\ValuesFactory;

use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Core\Exceptions\CreateJsonSchemaException;
use Sushi\ValueObject;
use Sushi\ValueObject\Invariant;

class JsonSchema extends ValueObject
{
    public function __construct(
        public readonly Json $value,
        private readonly JsonSchemaValidator $jsonSchemaValidator,
    ) {
        parent::__construct();
    }

    public function getValueAsString(): string
    {
        return $this->value->value;
    }

    #[Invariant]
    protected function isValidJsonSchema(): void
    {
        if (!$this->jsonSchemaValidator->isValid($this->value)) {
            throw CreateJsonSchemaException::notValidData($this->jsonSchemaValidator->getError());
        }
    }
}
