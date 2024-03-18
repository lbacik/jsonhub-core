<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\ValuesFactory;

use JsonHub\Core\Exceptions\CreateSlugException;
use JsonHub\Core\ValuesFactory\Slug;
use PhpSpec\ObjectBehavior;

class SlugSpec extends ObjectBehavior
{
    /** @dataProvider slugInputProvider */
    public function it_is_initializable($input, $isValid): void
    {
        $this->beConstructedWith($input);

        if ($isValid !== true) {
            $this->shouldThrow(CreateSlugException::class)->duringInstantiation();
        } else {
            $this->shouldHaveType(Slug::class);
        }
    }

    public function slugInputProvider(): array
    {
        return [
            [null, true],
            ['slug', true],
            ['slug.with.dots', false],
            ['slug with spaces', false],
            ['slug_with_underscores', true],
            ['slug-with-dashes', true],
            ['slug_with_underscores_and-dashes', true],
            ['123', true],
            ['slug%', false],
            ['slug!', false],
            ['slug?', false],
            ['slug\\', false],
            ['slug/', false],
            ['slug,', false],
            ['slug:', false],
            ['slug"', false],
            ['slug\'', false],
            ['slug@', false],
            ['slug~', false],
            ['slug`', false],
            ['slug^', false],
            ['slug_', true],
            ['slug-', true],
            ['slug__', false],
            ['slug-_slug', true],
            ['slug_-slug', true],
            ['slug-_slug_', false],
            ['slug_-slug-', false],
            ['slug-ą', false],
            ['slug-1234567890123456789012345678901234567890123456789012345678901234567890', false],
        ];
    }
}
