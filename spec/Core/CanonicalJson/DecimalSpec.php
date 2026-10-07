<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\CanonicalJson;

use JsonHub\Core\CanonicalJson\Internal\Decimal;
use PhpSpec\ObjectBehavior;

class DecimalSpec extends ObjectBehavior
{
    public function it_serializes_doubles_like_ecmascript_number_to_string(): void
    {
        // Includes the RFC 8785 Appendix B values that have no strict
        // literal: 1e21, 1e+30 and the maximum double only exist as doubles
        // (every literal denoting them is an unsafe integer), so they are
        // covered here at the serialization unit.
        $cases = [
            [0.0, '0'],
            [-0.0, '0'],
            [1.0, '1'],
            [1.1, '1.1'],
            [0.1, '0.1'],
            [0.5, '0.5'],
            [2.5, '2.5'],
            [4.5, '4.5'],
            [-1.5, '-1.5'],
            [100.5, '100.5'],
            [123.456, '123.456'],
            [100000.0, '100000'],
            [3.141592653589793, '3.141592653589793'],
            [333333333.33333329, '333333333.3333333'],
            [9007199254740991.0, '9007199254740991'],
            [0.002, '0.002'],
            [0.000001, '0.000001'],
            [0.0000001, '1e-7'],
            [1e-7, '1e-7'],
            [1.5e-7, '1.5e-7'],
            [1e20, '100000000000000000000'],
            [123456789012345680000.0, '123456789012345680000'],
            [1e21, '1e+21'],
            [1e22, '1e+22'],
            [9.999999999999999e22, '1e+23'],
            [1e30, '1e+30'],
            [5e-324, '5e-324'],
            [-5e-324, '-5e-324'],
            [2.2250738585072014e-308, '2.2250738585072014e-308'],
            [1.7976931348623157e308, '1.7976931348623157e+308'],
        ];
        foreach ($cases as [$double, $expected]) {
            $actual = Decimal::esToString($double);
            if ($actual !== $expected) {
                throw new \RuntimeException(
                    'esToString(' . var_export($double, true) . ') is ' . $actual
                );
            }
            if ((float) $actual !== $double && !($double == 0.0 && (float) $actual == 0.0)) {
                throw new \RuntimeException('Serialization of ' . $expected . ' does not round-trip');
            }
        }
    }

    public function it_classifies_literals_for_strict_reading(): void
    {
        $cases = [
            ['1', 'ok', '1'],
            ['1.0', 'ok', '1'],
            ['1e0', 'ok', '1'],
            ['-0', 'ok', '0'],
            ['0.1', 'ok', '0.1'],
            ['-2.5', 'ok', '-2.5'],
            ['9007199254740991', 'ok', '9007199254740991'],
            ['9007199254740992', 'unsafe_integer', '0'],
            ['9007199254740992.0', 'unsafe_integer', '0'],
            ['1e16', 'unsafe_integer', '0'],
            ['1e21', 'unsafe_integer', '0'],
            ['1e300', 'unsafe_integer', '0'],
            ['-9007199254740993', 'unsafe_integer', '0'],
            ['1e400', 'number_overflow', '0'],
            ['-1e400', 'number_overflow', '0'],
            ['0.10000000000000000001', 'precision_loss', '0'],
            ['1e-400', 'precision_loss', '0'],
            ['333333333.33333329', 'precision_loss', '0'],
        ];
        foreach ($cases as [$literal, $status, $canonical]) {
            [$actualStatus, , $actualCanonical] = Decimal::classify($literal);
            if ($actualStatus !== $status || $actualCanonical !== $canonical) {
                throw new \RuntimeException(
                    'classify(' . $literal . ') is [' . $actualStatus . ', ' . $actualCanonical . ']'
                );
            }
        }
    }

    public function it_normalizes_legacy_numbers_without_a_double(): void
    {
        $cases = [
            ['9007199254740993', '9007199254740993'],
            ['1e400', '1e400'],
            ['0.10000000000000000001', '0.10000000000000000001'],
            ['1E16', '1e16'],
            ['1e+16', '1e16'],
            ['1e016', '1e16'],
            ['9007199254740993e0', '9007199254740993'],
            ['1.50e3', '1.5e3'],
            ['-0.00e-0', '-0'],
        ];
        foreach ($cases as [$literal, $expected]) {
            $actual = Decimal::normalizeLiteral($literal);
            if ($actual !== $expected) {
                throw new \RuntimeException(
                    'normalizeLiteral(' . $literal . ') is ' . $actual
                );
            }
        }
    }
}
