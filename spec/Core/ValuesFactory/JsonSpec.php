<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\ValuesFactory;

use JsonException;
use JsonHub\Core\ValuesFactory\Json;
use PhpSpec\ObjectBehavior;

class JsonSpec extends ObjectBehavior
{
    /** @dataProvider initDataProvider */
    public function it_is_initializable($input): void
    {
        $this->beConstructedWith($input);
        $this->shouldHaveType(Json::class);
    }

    public function initDataProvider(): array
    {
        return [
            ['12'],
            ['"value"'],
            ['{"key": "value"}'],
            ['["value"]'],
            ['null'],
            ['true'],
            ['false'],
            [''],
            ['[]'],
            ['{}'],
        ];
    }

    public function it_is_not_initializable_for_invalid_json()
    {
        $this->beConstructedWith("'invalid'");
        $this->shouldThrow(JsonException::class)->duringInstantiation();
    }
}
