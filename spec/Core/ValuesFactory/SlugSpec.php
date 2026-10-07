<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\ValuesFactory;

use JsonHub\Core\Exceptions\CreateSlugException;
use JsonHub\Core\ValuesFactory\Slug;
use PhpSpec\ObjectBehavior;

class SlugSpec extends ObjectBehavior
{
    private const VALID_INPUTS = [
        null,
        'slug',
        'slug-with-dashes',
        '123',
        'slug-',
        '-slug',
        'slug--slug',
        'abcdefghijklmnopqrstuvwxyz012345',
    ];

    private const INVALID_INPUTS = [
        '',
        'Slug',
        'slug.with.dots',
        'slug with spaces',
        'slug_with_underscores',
        'slug_',
        'slug-_slug',
        'slug%',
        'slug!',
        'slug?',
        'slug\\',
        'slug/',
        'slug,',
        'slug:',
        'slug"',
        'slug\'',
        'slug@',
        'slug~',
        'slug`',
        'slug^',
        'slug-ą',
        'abcdefghijklmnopqrstuvwxyz0123456',
    ];

    public function it_is_initializable(): void
    {
        $this->beConstructedWith('slug');
        $this->shouldHaveType(Slug::class);
    }

    public function it_accepts_valid_input(): void
    {
        foreach (self::VALID_INPUTS as $input) {
            $this->shouldNotThrow()->during('__construct', [$input]);
        }
    }

    public function it_rejects_invalid_input(): void
    {
        foreach (self::INVALID_INPUTS as $input) {
            $this->shouldThrow(CreateSlugException::class)->during('__construct', [$input]);
        }
    }
}
