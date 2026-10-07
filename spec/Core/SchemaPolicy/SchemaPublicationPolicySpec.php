<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\SchemaPolicy;

use JsonHub\Contracts\SchemaLibraryCheck;
use JsonHub\Core\SchemaPolicy\SchemaPublicationPolicy;
use JsonHub\Core\SchemaPolicy\SchemaViolation;
use JsonHub\Core\SchemaPolicy\SchemaViolationCode;
use JsonHub\Core\SchemaPolicy\SchemaViolations;
use JsonHub\Core\ValuesFactory\Json;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use RuntimeException;
use Throwable;

class SchemaPublicationPolicySpec extends ObjectBehavior
{
    private const LATER_DIALECT_KEYWORDS = [
        'if',
        'then',
        'else',
        'unevaluatedProperties',
        'unevaluatedItems',
        'dependentRequired',
        'dependentSchemas',
        'prefixItems',
        '$dynamicRef',
        '$dynamicAnchor',
        '$recursiveRef',
        '$recursiveAnchor',
        '$anchor',
        'const',
        'contains',
        'propertyNames',
        'minContains',
        'maxContains',
        'contentSchema',
    ];

    public function it_is_initializable(SchemaLibraryCheck $library): void
    {
        $this->beConstructedWith($library);
        $this->shouldHaveType(SchemaPublicationPolicy::class);
    }

    public function it_accepts_a_schema_without_schema_declaration(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations('{"type":"object"}', []);
    }

    public function it_accepts_either_draft_04_declaration(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations('{"$schema":"http://json-schema.org/draft-04/schema#"}', []);
        $this->assertViolations('{"$schema":"http://json-schema.org/draft-04/schema"}', []);
    }

    public function it_rejects_a_later_dialect_declaration(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations(
            '{"$schema":"https://json-schema.org/draft/2020-12/schema"}',
            [['unsupported_dialect', '/$schema']],
        );
    }

    public function it_rejects_a_non_string_schema_declaration(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations('{"$schema":42}', [['unsupported_dialect', '/$schema']]);
        $this->assertViolations('{"$schema":null}', [['unsupported_dialect', '/$schema']]);
    }

    public function it_ignores_schema_declarations_in_subschemas(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations(
            '{"properties":{"a":{"$schema":"https://json-schema.org/draft/2020-12/schema"}}}',
            [],
        );
    }

    public function it_rejects_every_later_dialect_keyword(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        foreach (self::LATER_DIALECT_KEYWORDS as $keyword) {
            $schema = json_encode([$keyword => true]);
            assert(is_string($schema));
            $this->assertViolations($schema, [['unsupported_keyword', '/' . $keyword]]);
        }
    }

    public function it_rejects_later_dialect_keywords_in_subschemas(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations(
            '{"properties":{"a":{"const":1}}}',
            [['unsupported_keyword', '/properties/a/const']],
        );
        $this->assertViolations(
            '{"items":{"contains":{}}}',
            [['unsupported_keyword', '/items/contains']],
        );
    }

    public function it_accepts_a_property_named_like_a_later_dialect_keyword(
        SchemaLibraryCheck $library,
    ): void {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations('{"properties":{"if":{}}}', []);
    }

    public function it_ignores_extension_keywords_and_defs_containers(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations('{"x-custom":{"if":{}}}', []);
        $this->assertViolations('{"$defs":{"a":{"type":"string"}}}', []);
    }

    public function it_accepts_a_local_root_reference(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations('{"properties":{"child":{"$ref":"#"}}}', []);
    }

    public function it_passes_library_violations_through(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations([
            new SchemaViolation(SchemaViolationCode::InvalidKeywordValue, '/type', 'Not a valid type.'),
        ]));
        $this->beConstructedWith($library);

        $this->assertViolations('{"type":42}', [['invalid_keyword_value', '/type']]);
    }

    public function it_passes_invalid_patterns_through(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations([
            new SchemaViolation(SchemaViolationCode::InvalidPattern, '/pattern', 'Invalid pattern.'),
        ]));
        $this->beConstructedWith($library);

        $this->assertViolations('{"pattern":"[invalid"}', [['invalid_pattern', '/pattern']]);
    }

    public function it_does_not_call_the_library_after_a_safety_violation(
        SchemaLibraryCheck $library,
    ): void {
        $library->check(Argument::cetera())->shouldNotBeCalled();
        $this->beConstructedWith($library);

        $this->assertViolations(
            '{"$ref":"https://example.com/schema.json"}',
            [['external_reference', '/$ref']],
        );
    }

    public function it_converts_a_library_throwable_into_invalid_schema(
        SchemaLibraryCheck $library,
    ): void {
        $library->check(Argument::any())->willThrow(new RuntimeException('boom'));
        $this->beConstructedWith($library);

        $this->assertViolations('{"type":"object"}', [['invalid_schema', '']]);
    }

    public function it_drops_generic_invalid_schema_alongside_other_violations(
        SchemaLibraryCheck $library,
    ): void {
        $library->check(Argument::any())->willReturn(new SchemaViolations([
            new SchemaViolation(SchemaViolationCode::InvalidSchema, '', 'Generic failure.'),
        ]));
        $this->beConstructedWith($library);

        $this->assertViolations('{"const":1}', [['unsupported_keyword', '/const']]);
    }

    public function it_rejects_an_a_to_b_to_a_cycle(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::cetera())->shouldNotBeCalled();
        $this->beConstructedWith($library);

        $schema = '{"definitions":{"a":{"$ref":"#/definitions/b"},"b":{"$ref":"#/definitions/a"}},'
            . '"$ref":"#/definitions/a"}';

        $this->assertViolations($schema, [['reference_cycle', '/definitions/b/$ref']]);
    }

    public function it_rejects_missing_reference_targets(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::cetera())->shouldNotBeCalled();
        $this->beConstructedWith($library);

        $this->assertViolations(
            '{"definitions":{"a":{}},"$ref":"#/definitions/missing"}',
            [['unresolved_reference', '/$ref']],
        );
    }

    public function it_rejects_deep_schemas_without_calling_the_library(
        SchemaLibraryCheck $library,
    ): void {
        $library->check(Argument::cetera())->shouldNotBeCalled();
        $this->beConstructedWith($library);

        $nested = '1';
        for ($i = 0; $i < 64; $i++) {
            $nested = '{"a":' . $nested . '}';
        }

        $this->assertViolations($nested, [['schema_too_deep', '/' . implode('/', array_fill(0, 64, 'a'))]]);
    }

    public function it_rejects_non_object_roots_without_calling_the_library(
        SchemaLibraryCheck $library,
    ): void {
        $library->check(Argument::cetera())->shouldNotBeCalled();
        $this->beConstructedWith($library);

        $this->assertViolations('[]', [['invalid_keyword_value', '']]);
    }

    public function it_rejects_unsupported_identifiers(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $this->assertViolations(
            '{"$id":"http://example.com/root"}',
            [['unsupported_identifier', '/$id']],
        );
    }

    public function it_rejects_unsupported_fragment_references(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::cetera())->shouldNotBeCalled();
        $this->beConstructedWith($library);

        $this->assertViolations('{"$ref":"#name"}', [['unsupported_reference', '/$ref']]);
    }

    public function it_caps_violations_at_100(SchemaLibraryCheck $library): void
    {
        $library->check(Argument::any())->willReturn(new SchemaViolations());
        $this->beConstructedWith($library);

        $properties = [];
        for ($i = 0; $i < 150; $i++) {
            $properties['p' . $i] = ['const' => 1];
        }
        $schema = json_encode(['properties' => $properties]);
        assert(is_string($schema));

        $wrapped = $this->check(new Json($schema))->getWrappedObject();
        assert($wrapped instanceof SchemaViolations);
        if (count($wrapped) !== 100) {
            throw new RuntimeException('Expected exactly 100 violations, got ' . count($wrapped));
        }
    }

    /** @param list<array{string, string}> $expected */
    private function assertViolations(string $json, array $expected): void
    {
        $actual = array_map(
            static fn (SchemaViolation $violation): array => [$violation->code->value, $violation->pointer],
            $this->violationsOf($json)->toArray(),
        );
        if ($actual !== $expected) {
            throw new RuntimeException(
                'Expected ' . json_encode($expected) . ', got ' . json_encode($actual) . ' for ' . $json,
            );
        }
    }

    private function violationsOf(string $json): SchemaViolations
    {
        $wrapped = $this->check(new Json($json))->getWrappedObject();
        assert($wrapped instanceof SchemaViolations);

        return $wrapped;
    }
}
