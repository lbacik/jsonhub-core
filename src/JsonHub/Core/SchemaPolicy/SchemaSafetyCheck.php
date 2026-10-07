<?php

declare(strict_types=1);

namespace JsonHub\Core\SchemaPolicy;

use JsonHub\Core\ValuesFactory\Json;

/**
 * Decides whether a candidate schema may be handed to the validator library.
 *
 * Pure: no I/O, no library calls. Reports local-only resolvable references,
 * non-consuming reference cycles, base-URI identifiers and the depth limit.
 */
final class SchemaSafetyCheck
{
    public function check(Json $schema): SchemaViolations
    {
        $document = new SchemaDocument($schema);

        /** @var list<array{int, int, int, SchemaViolation}> */
        $found = [];
        $add = function (SchemaViolation $violation, int $guard) use (&$found, $document): void {
            $found[] = [$document->ranks[$violation->pointer] ?? PHP_INT_MAX, $guard, count($found), $violation];
        };

        if (!is_object($document->root)) {
            $add(
                new SchemaViolation(
                    SchemaViolationCode::InvalidKeywordValue,
                    '',
                    'Schema root must be an object.',
                ),
                1,
            );
        }

        foreach ($document->depthViolations as $violation) {
            $add($violation, 0);
        }

        foreach ($document->schemaNodes as [$pointer, $node]) {
            foreach (['id', '$id'] as $keyword) {
                if (property_exists($node, $keyword) && is_string($node->{$keyword})) {
                    $add(
                        new SchemaViolation(
                            SchemaViolationCode::UnsupportedIdentifier,
                            $pointer . '/' . $keyword,
                            'Identifier keyword "' . $keyword . '" is not supported.',
                        ),
                        2,
                    );
                }
            }
            if (property_exists($node, '$ref') && is_string($node->{'$ref'})) {
                [$code] = $document->dereference($node->{'$ref'});
                if ($code !== null) {
                    $add(
                        new SchemaViolation($code, $pointer . '/$ref', self::messageFor($code)),
                        match ($code) {
                            SchemaViolationCode::ExternalReference => 3,
                            SchemaViolationCode::UnsupportedReference => 4,
                            SchemaViolationCode::UnresolvedReference => 5,
                            default => 5,
                        },
                    );
                }
            }
        }

        foreach ($document->referenceCycles() as $violation) {
            $add($violation, 6);
        }

        usort(
            $found,
            static fn (array $left, array $right): int =>
                [$left[0], $left[1], $left[2]] <=> [$right[0], $right[1], $right[2]],
        );

        return new SchemaViolations(array_map(static fn (array $entry): SchemaViolation => $entry[3], $found));
    }

    private static function messageFor(SchemaViolationCode $code): string
    {
        return match ($code) {
            SchemaViolationCode::ExternalReference =>
                'External references are not supported; only local "#" references are allowed.',
            SchemaViolationCode::UnsupportedReference =>
                'Fragment references must be JSON Pointers ("#" or "#/...").',
            SchemaViolationCode::UnresolvedReference => 'Reference target does not exist.',
            default => 'Invalid reference.',
        };
    }
}
