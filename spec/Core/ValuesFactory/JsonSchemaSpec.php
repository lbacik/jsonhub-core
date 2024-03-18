<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\ValuesFactory;

use JsonHub\Contracts\JsonSchemaValidator;
use JsonHub\Core\Exceptions\CreateJsonSchemaException;
use JsonHub\Core\ValuesFactory\Json;
use JsonHub\Core\ValuesFactory\JsonSchema;
use PhpSpec\ObjectBehavior;

class JsonSchemaSpec extends ObjectBehavior
{
    private const FAKE_JSON_SCHEMA = '{}';

    public function it_is_initializable(JsonSchemaValidator $jsonSchemaValidator)
    {
        $json = new Json(self::FAKE_JSON_SCHEMA);

        $jsonSchemaValidator->isValid($json)->willReturn(true);
        $this->beConstructedWith($json, $jsonSchemaValidator);

        $this->shouldHaveType(JsonSchema::class);
    }

    public function it_is_not_initializable_when_json_is_not_valid_json_schema(JsonSchemaValidator $jsonSchemaValidator)
    {
        $json = new Json(self::FAKE_JSON_SCHEMA);

        $jsonSchemaValidator->isValid($json)->willReturn(false);
        $jsonSchemaValidator->getError()->willReturn('error');

        $this->beConstructedWith($json, $jsonSchemaValidator);

        $this->shouldThrow(CreateJsonSchemaException::notValidData('error'))->duringInstantiation();
    }
}
