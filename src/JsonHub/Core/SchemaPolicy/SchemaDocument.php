<?php

declare(strict_types=1);

namespace JsonHub\Core\SchemaPolicy;

use JsonHub\Core\Types\Json;

/**
 * A decoded candidate schema with its schema positions indexed.
 *
 * Decoding keeps JSON objects distinct from JSON arrays and never touches
 * the validator library. Containers (`definitions`, `$defs`) are transparent
 * for reference following; instance-consuming keywords (`properties`,
 * `patternProperties`, `additionalProperties`, `items`, `additionalItems`)
 * are not.
 *
 * @internal Shared by the safety check and the publication policy.
 */
final class SchemaDocument
{
    public const MAX_DEPTH = 64;

    public readonly mixed $root;

    /** @var list<array{string, object}> Pre-order [pointer, node] of objects at schema positions. */
    public readonly array $schemaNodes;

    /** @var list<SchemaViolation> */
    public readonly array $depthViolations;

    /** @var array<string, int> JSON Pointer => document pre-order rank. */
    public readonly array $ranks;

    public function __construct(Json $schema)
    {
        $root = json_decode($schema->value, false, 512, JSON_THROW_ON_ERROR);

        $nodes = [];
        $depthViolations = [];
        $ranks = [];
        $rank = 0;

        $visitValue = function (
            mixed $value,
            string $pointer,
            int $depth,
            bool $isSchemaPosition,
        ) use (
            &$visitValue,
            &$nodes,
            &$depthViolations,
            &$ranks,
            &$rank,
        ): void {
            if (!array_key_exists($pointer, $ranks)) {
                $ranks[$pointer] = $rank;
                $rank++;
            }
            if ($depth > self::MAX_DEPTH) {
                $depthViolations[] = new SchemaViolation(
                    SchemaViolationCode::SchemaTooDeep,
                    $pointer,
                    'Schema exceeds the maximum nesting depth of ' . self::MAX_DEPTH . '.',
                );

                return;
            }
            if (is_object($value)) {
                if ($isSchemaPosition) {
                    $nodes[] = [$pointer, $value];
                    $this->visitSchemaChildren($value, $pointer, $depth, $visitValue);
                } else {
                    foreach (get_object_vars($value) as $key => $child) {
                        $visitValue($child, $pointer . '/' . self::escape((string) $key), $depth + 1, false);
                    }
                }
            } elseif (is_array($value)) {
                foreach ($value as $index => $child) {
                    $visitValue($child, $pointer . '/' . self::escape((string) $index), $depth + 1, false);
                }
            }
        };
        $visitValue($root, '', 1, is_object($root));

        $this->root = $root;
        $this->schemaNodes = $nodes;
        $this->depthViolations = $depthViolations;
        $this->ranks = $ranks;
    }

    public static function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }

    /**
     * Classify a `$ref` value.
     *
     * @return array{SchemaViolationCode|null, string|null} Violation code, or null with the
     *                                                      normalized target pointer on success.
     */
    public function dereference(string $ref): array
    {
        if (!str_starts_with($ref, '#')) {
            return [SchemaViolationCode::ExternalReference, null];
        }
        if ($ref === '#') {
            return [null, ''];
        }
        if (!str_starts_with($ref, '#/')) {
            return [SchemaViolationCode::UnsupportedReference, null];
        }

        $current = $this->root;
        $decoded = [];
        foreach (explode('/', substr($ref, 2)) as $part) {
            $token = str_replace(['~1', '~0'], ['/', '~'], rawurldecode($part));
            $decoded[] = $token;
            if (is_object($current)) {
                $members = get_object_vars($current);
                if (!array_key_exists($token, $members)) {
                    return [SchemaViolationCode::UnresolvedReference, null];
                }
                $current = $members[$token];
            } elseif (is_array($current)) {
                if (!self::isArrayIndex($token) || (int) $token >= count($current)) {
                    return [SchemaViolationCode::UnresolvedReference, null];
                }
                $current = $current[(int) $token];
            } else {
                return [SchemaViolationCode::UnresolvedReference, null];
            }
        }

        return [null, '/' . implode('/', array_map(self::escape(...), $decoded))];
    }

    /**
     * Non-instance-consuming outgoing edges of a schema node in document order.
     *
     * @return list<array{string, bool, string|null}> [target pointer, is $ref edge, closing $ref pointer]
     */
    public function outgoingEdges(string $pointer, object $node): array
    {
        $edges = [];
        foreach (get_object_vars($node) as $keyword => $value) {
            if ($keyword === '$ref') {
                if (is_string($value)) {
                    [$code, $target] = $this->dereference($value);
                    if ($code === null) {
                        assert(is_string($target));
                        $edges[] = [$target, true, $pointer . '/$ref'];
                    }
                }

                continue;
            }
            if (($keyword === 'definitions' || $keyword === '$defs') && is_object($value)) {
                foreach (get_object_vars($value) as $name => $child) {
                    if (is_object($child)) {
                        $edges[] = [
                            $pointer . '/' . self::escape($keyword) . '/' . self::escape((string) $name),
                            false,
                            null,
                        ];
                    }
                }
            } elseif (
                ($keyword === 'allOf' || $keyword === 'anyOf' || $keyword === 'oneOf')
                && is_array($value)
            ) {
                foreach ($value as $index => $child) {
                    if (is_object($child)) {
                        $edges[] = [
                            $pointer . '/' . self::escape($keyword) . '/' . self::escape((string) $index),
                            false,
                            null,
                        ];
                    }
                }
            } elseif ($keyword === 'not' && is_object($value)) {
                $edges[] = [$pointer . '/not', false, null];
            } elseif ($keyword === 'dependencies' && is_object($value)) {
                foreach (get_object_vars($value) as $name => $child) {
                    if (is_object($child)) {
                        $edges[] = [
                            $pointer . '/dependencies/' . self::escape((string) $name),
                            false,
                            null,
                        ];
                    }
                }
            }
        }

        return $edges;
    }

    /**
     * One `reference_cycle` violation per cycle that can be followed without
     * consuming instance data, reported at the `$ref` that closes it.
     *
     * @return list<SchemaViolation>
     */
    public function referenceCycles(): array
    {
        $nodeByPointer = [];
        foreach ($this->schemaNodes as [$pointer, $node]) {
            $nodeByPointer[$pointer] = $node;
        }

        $color = [];
        $reported = [];
        $cycles = [];

        $visit = function (string $pointer) use (&$visit, &$color, &$reported, &$cycles, $nodeByPointer): void {
            $color[$pointer] = 'gray';
            $node = $nodeByPointer[$pointer] ?? null;
            if ($node !== null) {
                foreach ($this->outgoingEdges($pointer, $node) as [$target, $isRef, $closing]) {
                    $targetColor = $color[$target] ?? 'white';
                    if ($targetColor === 'gray') {
                        if ($isRef && $closing !== null && !isset($reported[$closing])) {
                            $reported[$closing] = true;
                            $cycles[] = new SchemaViolation(
                                SchemaViolationCode::ReferenceCycle,
                                $closing,
                                'Reference cycle does not consume instance data and would not terminate.',
                            );
                        }

                        continue;
                    }
                    if ($targetColor === 'white') {
                        $visit($target);
                    }
                }
            }
            $color[$pointer] = 'black';
        };

        foreach (array_keys($nodeByPointer) as $pointer) {
            if (($color[$pointer] ?? 'white') === 'white') {
                $visit($pointer);
            }
        }

        return $cycles;
    }

    /** @param callable(mixed, string, int, bool): void $visitValue */
    private function visitSchemaChildren(object $node, string $pointer, int $depth, callable $visitValue): void
    {
        foreach (get_object_vars($node) as $keyword => $value) {
            if (
                ($keyword === 'properties' || $keyword === 'patternProperties'
                    || $keyword === 'definitions' || $keyword === '$defs')
                && is_object($value)
            ) {
                foreach (get_object_vars($value) as $name => $child) {
                    $visitValue(
                        $child,
                        $pointer . '/' . self::escape($keyword) . '/' . self::escape((string) $name),
                        $depth + 1,
                        is_object($child),
                    );
                }
            } elseif (
                ($keyword === 'allOf' || $keyword === 'anyOf' || $keyword === 'oneOf')
                && is_array($value)
            ) {
                foreach ($value as $index => $child) {
                    $visitValue(
                        $child,
                        $pointer . '/' . self::escape($keyword) . '/' . self::escape((string) $index),
                        $depth + 1,
                        is_object($child),
                    );
                }
            } elseif (
                ($keyword === 'additionalProperties' || $keyword === 'additionalItems' || $keyword === 'not')
                && is_object($value)
            ) {
                $visitValue($value, $pointer . '/' . self::escape($keyword), $depth + 1, true);
            } elseif ($keyword === 'items') {
                if (is_object($value)) {
                    $visitValue($value, $pointer . '/items', $depth + 1, true);
                } elseif (is_array($value)) {
                    foreach ($value as $index => $child) {
                        $visitValue(
                            $child,
                            $pointer . '/items/' . self::escape((string) $index),
                            $depth + 1,
                            is_object($child),
                        );
                    }
                } else {
                    $visitValue($value, $pointer . '/items', $depth + 1, false);
                }
            } elseif ($keyword === 'dependencies' && is_object($value)) {
                foreach (get_object_vars($value) as $name => $child) {
                    $visitValue(
                        $child,
                        $pointer . '/dependencies/' . self::escape((string) $name),
                        $depth + 1,
                        is_object($child),
                    );
                }
            } else {
                $visitValue($value, $pointer . '/' . self::escape($keyword), $depth + 1, false);
            }
        }
    }

    private static function isArrayIndex(string $token): bool
    {
        return $token !== '' && ctype_digit($token) && ($token === '0' || !str_starts_with($token, '0'));
    }
}
