<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\Types;

use JsonException;
use JsonHub\Core\Types\Json;
use PhpSpec\ObjectBehavior;

class JsonSpec extends ObjectBehavior
{
    private const VALID_INPUTS = [
        '12',
        '"value"',
        '{"key": "value"}',
        '["value"]',
        'null',
        'true',
        'false',
        '[]',
        '{}',
    ];

    private const INVALID_INPUTS = [
        '',
        "'invalid'",
        '{"key": }',
        '[1, 2',
    ];

    public function it_is_initializable(): void
    {
        $this->beConstructedWith('{}');
        $this->shouldHaveType(Json::class);
    }

    public function it_accepts_valid_json(): void
    {
        foreach (self::VALID_INPUTS as $input) {
            $this->shouldNotThrow()->during('__construct', [$input]);
        }
    }

    public function it_rejects_invalid_json(): void
    {
        foreach (self::INVALID_INPUTS as $input) {
            $this->shouldThrow(JsonException::class)->during('__construct', [$input]);
        }
    }

    public function it_decodes_a_json_object(): void
    {
        $this->beConstructedWith('{"key": "value"}');
        $this->decode()->shouldBeLike((object) ['key' => 'value']);
    }
}
