<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\CanonicalJson;

use JsonHub\Core\CanonicalJson\CanonicalJson;
use JsonHub\Core\CanonicalJson\CanonicalJsonException;
use JsonHub\Core\CanonicalJson\ChargeEncoding;
use JsonHub\Core\CanonicalJson\DraftSizeLimit;
use JsonHub\Core\CanonicalJson\SchemaCharge;
use JsonHub\Core\CanonicalJson\Violation;
use PhpSpec\ObjectBehavior;

class CanonicalJsonSpec extends ObjectBehavior
{
    public function it_reads_strict_values_into_canonical_form(): void
    {
        $cases = [
            ['{"b":1,"a":2}', '{"a":2,"b":1}'],
            [" [ 1 , 2.0 , 1e0 , -0 ] ", '[1,2,1,0]'],
            ['{"x\\/y":1}', '{"x/y":1}'],
        ];
        foreach ($cases as [$input, $expected]) {
            $this->assertStrictBytes($input, $expected);
        }

        $this->assertStrictBytes('"é"', '"é"');
        $this->assertStrictBytes('"\\u00e9"', '"é"');
        $this->assertStrictBytes('{}', '{}');
        $this->assertStrictBytes('[]', '[]');
    }

    public function it_serializes_strict_numbers_like_ecmascript(): void
    {
        // JCS number cases from RFC 8785 Appendix B inside the safe range,
        // plus the brief's extra cases. Larger Appendix B values (1e21,
        // 1e+30, the maximum double) are covered as strict rejections below
        // and as serialization units in DecimalSpec: any literal denoting
        // an integer beyond 2^53 - 1 fails the new-write rules.
        $cases = [
            '0' => '0',
            '1' => '1',
            '1.0' => '1',
            '1e0' => '1',
            '-0' => '0',
            '0.0' => '0',
            '0e5' => '0',
            '-0.0e5' => '0',
            '1.1' => '1.1',
            '1.50' => '1.5',
            '4.50' => '4.5',
            '2e-3' => '0.002',
            '0.1' => '0.1',
            '1e-7' => '1e-7',
            '1.5e-7' => '1.5e-7',
            '0.000001' => '0.000001',
            '0.0000001' => '1e-7',
            '5e-324' => '5e-324',
            '-5e-324' => '-5e-324',
            '2.2250738585072014e-308' => '2.2250738585072014e-308',
            '9.999999999999997e-7' => '9.999999999999997e-7',
            '333333333.3333333' => '333333333.3333333',
            '123.456' => '123.456',
            '100.5' => '100.5',
            '-0.5' => '-0.5',
            '9007199254740991' => '9007199254740991',
        ];
        foreach ($cases as $input => $expected) {
            $this->assertStrictBytes((string) $input, $expected);
        }
    }

    public function it_rejects_duplicate_member_names(): void
    {
        $this->assertViolations('{"a":1,"a":2}', true, [['duplicate_member', '/a']]);
    }

    public function it_reads_exponents_containing_nine_in_both_modes(): void
    {
        $this->assertStrictBytes('1e-9', '1e-9');
        $this->assertLegacyBytes('1e-9', '1e-9', ChargeEncoding::Jcs);
    }

    public function it_preserves_negative_and_fractional_legacy_numbers(): void
    {
        $this->assertLegacyBytes('-9007199254740993', '-9007199254740993', ChargeEncoding::Legacy);
        $this->assertLegacyBytes('0.90000000000000000001', '0.90000000000000000001', ChargeEncoding::Legacy);
        $this->assertLegacyBytes('1e-999', '1e-999', ChargeEncoding::Legacy);
        $this->assertStrictBytes('-9007199254740991', '-9007199254740991');
    }

    public function it_detects_duplicates_by_decoded_name(): void
    {
        $this->assertViolations('{"\\u0041":1,"A":2}', true, [['duplicate_member', '/A']]);
        $this->assertViolations('{"a/b":1,"a/b":2}', true, [['duplicate_member', '/a~1b']]);
    }

    public function it_rejects_unsafe_integers_in_any_notation(): void
    {
        $this->assertViolations('{"n":9007199254740992}', true, [['unsafe_integer', '/n']]);
        $this->assertViolations('{"n":9007199254740992.0}', true, [['unsafe_integer', '/n']]);
        $this->assertViolations('{"n":1e16}', true, [['unsafe_integer', '/n']]);
        $this->assertViolations('{"n":1e300}', true, [['unsafe_integer', '/n']]);
        $this->assertViolations('{"n":1E16}', true, [['unsafe_integer', '/n']]);
        $this->assertViolations('{"n":-9007199254740992}', true, [['unsafe_integer', '/n']]);
        // Exactly representable but outside the safe range: the new-write
        // rules reject by mathematical value, not by representability.
        $this->assertViolations('1e21', true, [['unsafe_integer', '']]);
        $this->assertViolations('1.7976931348623157e308', true, [['unsafe_integer', '']]);
        $this->assertStrictBytes('{"n":9007199254740991}', '{"n":9007199254740991}');
    }

    public function it_rejects_overflowing_numbers(): void
    {
        $this->assertViolations('{"n":1e400}', true, [['number_overflow', '/n']]);
        $this->assertViolations('{"n":-1e400}', true, [['number_overflow', '/n']]);
    }

    public function it_rejects_numbers_failing_the_round_trip_test(): void
    {
        $this->assertViolations('0.10000000000000000001', true, [['precision_loss', '']]);
        $this->assertViolations('1e-400', true, [['precision_loss', '']]);
        $this->assertViolations('333333333.33333329', true, [['precision_loss', '']]);
    }

    public function it_rejects_lone_surrogates_in_strict_mode(): void
    {
        $this->assertViolations('"\\ud800"', true, [['invalid_string', '']]);
        $this->assertViolations('"\\udc00"', true, [['invalid_string', '']]);
        $this->assertViolations('{"s":"\\ud800"}', true, [['invalid_string', '/s']]);
        $this->assertViolations('"\\ud800\\u0041"', true, [['invalid_string', '']]);
    }

    public function it_accepts_paired_surrogates_as_raw_utf8(): void
    {
        $this->assertStrictBytes('"\\ud83d\\ude00"', '"😀"');
    }

    public function it_preserves_space_and_unicode_encoding_boundaries(): void
    {
        $this->assertStrictBytes('" "', '" "');
        $this->assertStrictBytes('"\\u0080\\u0800\\ud800\\udc00"', '"' . "\u{80}\u{800}\u{10000}" . '"');
        $this->assertStrictBytes('"' . "\u{10000}\u{3FFFF}" . '"', '"' . "\u{10000}\u{3FFFF}" . '"');
    }

    public function it_rejects_invalid_utf8_continuations_inside_strings(): void
    {
        foreach (["\xC2A", "\xE1A\x80", "\xE1\x80A", "\xF0\x90A\x80", "\xF0\x90\x80A"] as $bytes) {
            $this->assertViolations('"' . $bytes . '"', true, [['invalid_json', '#1']]);
        }
    }

    public function it_reports_several_violations_together(): void
    {
        $this->assertViolations(
            '{"a":1,"a":2,"n":9007199254740992,"s":"\\ud800","f":1e400}',
            true,
            [
                ['duplicate_member', '/a'],
                ['unsafe_integer', '/n'],
                ['invalid_string', '/s'],
                ['number_overflow', '/f'],
            ]
        );
    }

    public function it_caps_collected_violations_at_100(): void
    {
        $members = [];
        for ($i = 0; $i < 150; $i++) {
            $members[] = '"k":' . $i;
        }
        try {
            CanonicalJson::strict('{' . implode(',', $members) . '}');
        } catch (CanonicalJsonException $exception) {
            if (count($exception->violations) !== 100) {
                throw new \RuntimeException(
                    'Expected 100 violations, got ' . count($exception->violations)
                );
            }

            return;
        }
        throw new \RuntimeException('Expected CanonicalJsonException to be thrown');
    }

    public function it_rejects_malformed_json_with_byte_offset(): void
    {
        $cases = [
            ['', '#0'],
            [' ', '#1'],
            ['{', '#1'],
            ['{"a":', '/a#5'],
            ['[1,', '#3'],
            ['{"a":1,}', '#7'],
            ['[1,2,]', '#5'],
            ['{"a"}', '#4'],
            ['tru', '#0'],
            ['01', '#1'],
            ['1.', '#2'],
            ['1e', '#2'],
            ['-', '#1'],
            ['"a', '#2'],
            ['"\\u12"', '#3'],
            ['"\\x"', '#2'],
            ["\"\n\"", '#1'],
            ["\xFF", '#0'],
            ["\xC0\xAF", '#0'],
            ["\xed\xa0\x80", '#0'],
            ['[1] [2]', '#4'],
            ['[1]x', '#3'],
            ['{"a": tru}', '/a#6'],
        ];
        foreach ($cases as [$input, $pointer]) {
            $this->assertViolations($input, true, [['invalid_json', $pointer]]);
        }
    }

    public function it_aborts_on_invalid_json_without_collecting(): void
    {
        // The duplicate would be reported on its own, but malformed input
        // aborts the read with only invalid_json.
        $this->assertViolations('{"a":1,"a":2, broken', true, [['invalid_json', '#14']]);
    }

    public function it_keeps_duplicates_stably_in_legacy_mode(): void
    {
        $this->assertLegacyBytes('{"a":1,"a":2}', '{"a":1,"a":2}', ChargeEncoding::Legacy);
        $this->assertLegacyBytes('{"a":2,"a":1}', '{"a":2,"a":1}', ChargeEncoding::Legacy);
        $this->assertLegacyBytes('{"b":1,"a":2,"a":3}', '{"a":2,"a":3,"b":1}', ChargeEncoding::Legacy);
    }

    public function it_preserves_legacy_numbers_exactly(): void
    {
        $this->assertLegacyBytes('9007199254740993', '9007199254740993', ChargeEncoding::Legacy);
        $this->assertLegacyBytes('1e400', '1e400', ChargeEncoding::Legacy);
        $this->assertLegacyBytes(
            '0.10000000000000000001',
            '0.10000000000000000001',
            ChargeEncoding::Legacy
        );
        $this->assertLegacyBytes('1E16', '1e16', ChargeEncoding::Legacy);
        $this->assertLegacyBytes('1e+16', '1e16', ChargeEncoding::Legacy);
    }

    public function it_preserves_lone_surrogates_in_legacy_mode(): void
    {
        $this->assertLegacyBytes('"\\ud800"', '"\\ud800"', ChargeEncoding::Legacy);
        $this->assertLegacyBytes('"\\udc00"', '"\\udc00"', ChargeEncoding::Legacy);
        $this->assertLegacyBytes(
            '{"a\\ud800b":1,"ab":2}',
            '{"ab":2,"a\\ud800b":1}',
            ChargeEncoding::Legacy
        );
    }

    public function it_reads_a_legacy_root_array_as_the_empty_object(): void
    {
        $this->assertLegacyBytes('[]', '{}', ChargeEncoding::Jcs);
        $this->assertLegacyBytes('[[]]', '[[]]', ChargeEncoding::Jcs);
        $this->assertLegacyBytes('[1]', '[1]', ChargeEncoding::Jcs);
        $this->assertStrictBytes('[]', '[]');
    }

    public function it_marks_legacy_results_with_encoding_1_when_strict_would_pass(): void
    {
        $this->assertLegacyBytes('{"b":1,"a":2}', '{"a":2,"b":1}', ChargeEncoding::Jcs);
        $this->assertLegacyBytes('1.0', '1', ChargeEncoding::Jcs);
        $this->assertLegacyBytes('1.5e3', '1500', ChargeEncoding::Jcs);
        $this->assertLegacyBytes('-0.0', '0', ChargeEncoding::Jcs);
    }

    public function it_reads_valid_input_identically_in_both_modes(): void
    {
        $input = '{"b": [1.0, "x\\/y", -0], "a": {"z": null, "y": true}}';
        $strict = CanonicalJson::strict($input);
        $legacy = CanonicalJson::legacy($input);
        if ($strict->bytes() !== $legacy->bytes()) {
            throw new \RuntimeException('Strict and legacy bytes differ: ' . $strict->bytes());
        }
        if ($strict->encoding() !== ChargeEncoding::Jcs || $legacy->encoding() !== ChargeEncoding::Jcs) {
            throw new \RuntimeException('Expected encoding 1 in both modes');
        }
        if ($strict->bytes() !== '{"a":{"y":true,"z":null},"b":[1,"x/y",0]}') {
            throw new \RuntimeException('Unexpected canonical form: ' . $strict->bytes());
        }
    }

    public function it_defines_equality_as_canonical_byte_identity(): void
    {
        $first = CanonicalJson::strict('{"b":1,"a":2}');
        $second = CanonicalJson::legacy('{"a":2,"b":1}');
        if (!$first->equals($second) || !$second->equals($first)) {
            throw new \RuntimeException('Expected equal canonical forms to be equal');
        }
        $other = CanonicalJson::strict('{"a":2,"b":1,"c":3}');
        if ($first->equals($other)) {
            throw new \RuntimeException('Expected different canonical forms to differ');
        }
        $legacyDuplicates = CanonicalJson::legacy('{"a":1,"a":2}');
        if (!$legacyDuplicates->equals(CanonicalJson::legacy('{"a":1,"a":2}'))) {
            throw new \RuntimeException('Expected identical legacy forms to be equal');
        }
        if ($legacyDuplicates->equals(CanonicalJson::strict('{"a":1}'))) {
            throw new \RuntimeException('Expected encoding-0 form to differ from encoding-1');
        }
    }

    public function it_gives_formatting_variants_the_same_charge_and_verdict(): void
    {
        $variants = [
            '{"b":1,"a":[1,2]}',
            '{ "a" : [ 1 , 2 ] , "b" : 1 }',
            '{"b":1.0,"a":[1e0,2.0]}',
        ];
        $charges = [];
        foreach ($variants as $variant) {
            $canonical = CanonicalJson::strict($variant);
            $charge = SchemaCharge::of($canonical);
            $charges[] = [$canonical->bytes(), $charge->bytes, $charge->encoding];
            (new DraftSizeLimit(100))->check($charge);
        }
        foreach ($charges as $charge) {
            if ($charge !== $charges[0]) {
                throw new \RuntimeException('Formatting variants differ in charge');
            }
        }
        if ($charges[0][0] !== '{"a":[1,2],"b":1}') {
            throw new \RuntimeException('Unexpected canonical form: ' . $charges[0][0]);
        }
    }

    public function it_sorts_members_by_utf16_code_units(): void
    {
        $this->assertStrictBytes('{"~":1,"a":2}', '{"a":2,"~":1}');
        $this->assertStrictBytes('{"é":1,"z":2}', '{"z":2,"é":1}');
        // U+1F600 is the UTF-16 pair D83D DE00, U+E000 is the single unit
        // E000; the first differing units decide, so the astral key sorts
        // first. This is the RFC 8785 (JavaScript string comparison) order,
        // not code-point order.
        $emoji = "\u{1F600}";
        $privateUse = "\u{E000}";
        $this->assertStrictBytes(
            '{"' . $privateUse . '":2,"' . $emoji . '":1}',
            '{"' . $emoji . '":1,"' . $privateUse . '":2}'
        );
    }

    public function it_reads_deeply_nested_legacy_input_without_a_crash(): void
    {
        $input = str_repeat('[', 100000) . str_repeat(']', 100000);
        $canonical = CanonicalJson::legacy($input);
        if (strlen($canonical->bytes()) !== 200000) {
            throw new \RuntimeException('Unexpected deep canonical length');
        }
        if ($canonical->encoding() !== ChargeEncoding::Jcs) {
            throw new \RuntimeException('Expected encoding 1 for valid deep input');
        }

        $strict = CanonicalJson::strict(str_repeat('[', 100000) . '1' . str_repeat(']', 100000));
        if (strlen($strict->bytes()) !== 200001) {
            throw new \RuntimeException('Unexpected deep strict canonical length');
        }
    }

    /**
     * @param list<array{string, string}> $expected
     */
    private function assertViolations(string $input, bool $strict, array $expected): void
    {
        try {
            if ($strict) {
                CanonicalJson::strict($input);
            } else {
                CanonicalJson::legacy($input);
            }
        } catch (CanonicalJsonException $exception) {
            $actual = array_map(
                static fn (Violation $violation): array => [$violation->code, $violation->pointer],
                $exception->violations
            );
            if ($actual !== $expected) {
                throw new \RuntimeException(
                    'Violations for ' . json_encode($input) . ' differ: ' . json_encode($actual)
                );
            }

            return;
        }
        throw new \RuntimeException('Expected CanonicalJsonException for ' . json_encode($input));
    }

    private function assertStrictBytes(string $input, string $expected): void
    {
        $canonical = CanonicalJson::strict($input);
        if ($canonical->bytes() !== $expected) {
            throw new \RuntimeException(
                'Canonical form of ' . json_encode($input) . ' is ' . $canonical->bytes()
            );
        }
        if ($canonical->encoding() !== ChargeEncoding::Jcs) {
            throw new \RuntimeException('Expected encoding 1 for ' . json_encode($input));
        }
    }

    private function assertLegacyBytes(string $input, string $expected, ChargeEncoding $encoding): void
    {
        $canonical = CanonicalJson::legacy($input);
        if ($canonical->bytes() !== $expected) {
            throw new \RuntimeException(
                'Legacy canonical form of ' . json_encode($input) . ' is ' . $canonical->bytes()
            );
        }
        if ($canonical->encoding() !== $encoding) {
            throw new \RuntimeException(
                'Expected encoding ' . $encoding->value . ' for ' . json_encode($input)
            );
        }
    }
}
