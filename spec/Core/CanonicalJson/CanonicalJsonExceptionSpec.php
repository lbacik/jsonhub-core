<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\CanonicalJson;

use JsonHub\Core\CanonicalJson\CanonicalJsonException;
use JsonHub\Core\CanonicalJson\Violation;
use PhpSpec\ObjectBehavior;

class CanonicalJsonExceptionSpec extends ObjectBehavior
{
    public function it_exposes_violations_with_stable_codes_and_pointers(): void
    {
        $exception = CanonicalJsonException::fromViolations([
            new Violation('duplicate_member', '/a'),
            new Violation('unsafe_integer', '/n'),
        ]);
        if (count($exception->violations) !== 2) {
            throw new \RuntimeException('Expected two violations');
        }
        if ($exception->violations[0]->code !== 'duplicate_member') {
            throw new \RuntimeException('Expected the duplicate_member code');
        }
        if ($exception->violations[1]->pointer !== '/n') {
            throw new \RuntimeException('Expected the /n pointer');
        }
    }

    public function it_builds_a_single_violation(): void
    {
        $exception = CanonicalJsonException::single('root_not_object', '');
        if (count($exception->violations) !== 1) {
            throw new \RuntimeException('Expected a single violation');
        }
        if (!str_contains($exception->getMessage(), 'root_not_object')) {
            throw new \RuntimeException('Expected the code in the message');
        }
    }

    public function it_is_an_invalid_argument_exception(): void
    {
        $exception = CanonicalJsonException::single('invalid_json', '#0');
        if (!$exception instanceof \InvalidArgumentException) {
            throw new \RuntimeException('Expected an InvalidArgumentException');
        }
    }
}
