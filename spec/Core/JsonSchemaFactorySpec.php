<?php

declare(strict_types=1);

namespace spec\JsonHub\Core;

use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Core\Exceptions\CreateJsonSchemaException;
use JsonHub\Core\JsonSchemaFactory;
use JsonHub\Core\Types\Json;
use JsonHub\Core\Types\JsonSchema;
use PhpSpec\ObjectBehavior;

class JsonSchemaFactorySpec extends ObjectBehavior
{
    private const EMPTY_JSON_OBJECT_AS_STRING = '{}';

    public function let(JsonSchemaValidator $jsonSchemaValidator)
    {
        $this->beConstructedWith($jsonSchemaValidator);
    }

    public function it_is_initializable(): void
    {
        $this->shouldHaveType(JsonSchemaFactory::class);
    }

    public function it_creates_json_schema(JsonSchemaValidator $jsonSchemaValidator): void
    {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))
            ->willReturn(true);

        $this->createJsonSchema(self::EMPTY_JSON_OBJECT_AS_STRING)
            ->shouldBeAnInstanceOf(JsonSchema::class);
    }

    public function it_throws_exception_when_creating_json_schema_from_invalid_schema(
        JsonSchemaValidator $jsonSchemaValidator,
    ): void {
        $jsonSchemaValidator->isValid(new Json(self::EMPTY_JSON_OBJECT_AS_STRING))
            ->willReturn(false);
        $jsonSchemaValidator->getError()->willReturn('error');

        $this->shouldThrow(CreateJsonSchemaException::notValidData('error'))
            ->during('createJsonSchema', [self::EMPTY_JSON_OBJECT_AS_STRING]);
    }
}
