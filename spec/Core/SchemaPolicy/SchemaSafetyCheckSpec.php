<?php

declare(strict_types=1);

namespace spec\JsonHub\Core\SchemaPolicy;

use JsonHub\Core\SchemaPolicy\SchemaSafetyCheck;
use JsonHub\Core\SchemaPolicy\SchemaViolation;
use JsonHub\Core\SchemaPolicy\SchemaViolations;
use JsonHub\Core\ValuesFactory\Json;
use PhpSpec\ObjectBehavior;
use RuntimeException;

class SchemaSafetyCheckSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldHaveType(SchemaSafetyCheck::class);
    }

    public function it_rejects_a_non_object_root(): void
    {
        $this->assertViolations('[]', [['invalid_keyword_value', '']]);
        $this->assertViolations('"just a string"', [['invalid_keyword_value', '']]);
        $this->assertViolations('42', [['invalid_keyword_value', '']]);
    }

    public function it_accepts_an_empty_object(): void
    {
        $this->assertViolations('{}', []);
    }

    public function it_rejects_raw_nesting_deeper_than_64(): void
    {
        $nested = '1';
        for ($i = 0; $i < 64; $i++) {
            $nested = '{"a":' . $nested . '}';
        }

        $this->assertViolations($nested, [['schema_too_deep', '/' . implode('/', array_fill(0, 64, 'a'))]]);
    }

    public function it_accepts_raw_nesting_of_exactly_64(): void
    {
        $nested = '1';
        for ($i = 0; $i < 63; $i++) {
            $nested = '{"a":' . $nested . '}';
        }

        $this->assertViolations($nested, []);
    }

    public function it_rejects_external_references(): void
    {
        $this->assertViolations(
            '{"$ref":"https://example.com/schema.json"}',
            [['external_reference', '/$ref']],
        );
        $this->assertViolations(
            '{"$ref":"other.json#/definitions/a"}',
            [['external_reference', '/$ref']],
        );
        $this->assertViolations(
            '{"properties":{"a":{"$ref":"http://example.com/x"}}}',
            [['external_reference', '/properties/a/$ref']],
        );
    }

    public function it_rejects_non_pointer_fragment_references(): void
    {
        $this->assertViolations('{"$ref":"#name"}', [['unsupported_reference', '/$ref']]);
    }

    public function it_rejects_unresolved_references(): void
    {
        $this->assertViolations(
            '{"definitions":{"a":{}},"$ref":"#/definitions/missing"}',
            [['unresolved_reference', '/$ref']],
        );
    }

    public function it_resolves_references_through_escaped_and_encoded_pointers(): void
    {
        $this->assertViolations(
            '{"definitions":{"a/b":{}},"$ref":"#/definitions/a~1b"}',
            [],
        );
        $this->assertViolations(
            '{"definitions":{"a~b":{}},"$ref":"#/definitions/a~0b"}',
            [],
        );
        $this->assertViolations(
            '{"definitions":{"a b":{}},"$ref":"#/definitions/a%20b"}',
            [],
        );
        $this->assertViolations(
            '{"allOf":[{"type":"string"}],"$ref":"#/allOf/0"}',
            [],
        );
    }

    public function it_rejects_a_direct_self_cycle(): void
    {
        $this->assertViolations('{"$ref":"#"}', [['reference_cycle', '/$ref']]);
    }

    public function it_rejects_an_a_to_b_to_a_cycle(): void
    {
        $schema = '{"definitions":{"a":{"$ref":"#/definitions/b"},"b":{"$ref":"#/definitions/a"}},'
            . '"$ref":"#/definitions/a"}';

        $this->assertViolations($schema, [['reference_cycle', '/definitions/b/$ref']]);
    }

    public function it_rejects_a_cycle_through_allOf(): void
    {
        $schema = '{"definitions":{"a":{"allOf":[{"$ref":"#/definitions/b"}]},'
            . '"b":{"allOf":[{"$ref":"#/definitions/a"}]}},"$ref":"#/definitions/a"}';

        $this->assertViolations($schema, [['reference_cycle', '/definitions/b/allOf/0/$ref']]);
    }

    public function it_rejects_a_cycle_through_siblings_of_a_ref_node(): void
    {
        $schema = '{"definitions":{"a":{"$ref":"#/definitions/b","allOf":[{"$ref":"#/definitions/a"}]},'
            . '"b":{}},"$ref":"#/definitions/a"}';

        $this->assertViolations($schema, [['reference_cycle', '/definitions/a/allOf/0/$ref']]);
    }

    public function it_rejects_a_cycle_through_not(): void
    {
        $schema = '{"definitions":{"a":{"not":{"$ref":"#/definitions/a"}}}}';

        $this->assertViolations($schema, [['reference_cycle', '/definitions/a/not/$ref']]);
    }

    public function it_rejects_a_cycle_through_object_dependencies(): void
    {
        $schema = '{"definitions":{"a":{"dependencies":{"x":{"$ref":"#/definitions/a"}}}}}';

        $this->assertViolations($schema, [['reference_cycle', '/definitions/a/dependencies/x/$ref']]);
    }

    public function it_allows_a_cycle_through_properties(): void
    {
        $this->assertViolations('{"properties":{"child":{"$ref":"#"}}}', []);
    }

    public function it_allows_a_cycle_through_items(): void
    {
        $this->assertViolations('{"items":[{"$ref":"#"}]}', []);
    }

    public function it_rejects_string_identifiers_at_schema_positions(): void
    {
        $this->assertViolations('{"id":"http://example.com/root"}', [['unsupported_identifier', '/id']]);
        $this->assertViolations('{"$id":"http://example.com/root"}', [['unsupported_identifier', '/$id']]);
        $this->assertViolations(
            '{"definitions":{"a":{"$id":"http://example.com/a"}}}',
            [['unsupported_identifier', '/definitions/a/$id']],
        );
    }

    public function it_ignores_non_string_identifiers(): void
    {
        $this->assertViolations('{"id":42}', []);
    }

    public function it_ignores_refs_outside_schema_positions(): void
    {
        $this->assertViolations('{"enum":[{"$ref":"https://example.com/x"}]}', []);
        $this->assertViolations('{"x-custom":{"$ref":"https://example.com/x"}}', []);
    }

    public function it_caps_violations_at_100(): void
    {
        $properties = [];
        for ($i = 0; $i < 150; $i++) {
            $properties['p' . $i] = ['$ref' => 'https://example.com/' . $i];
        }
        $schema = json_encode(['properties' => $properties]);
        assert(is_string($schema));

        $violations = $this->violationsOf($schema);
        if (count($violations) !== 100) {
            throw new RuntimeException('Expected exactly 100 violations, got ' . count($violations));
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
