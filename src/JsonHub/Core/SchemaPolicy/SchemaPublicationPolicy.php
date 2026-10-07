<?php

declare(strict_types=1);

namespace JsonHub\Core\SchemaPolicy;

use JsonHub\Contracts\SchemaLibraryCheck;
use JsonHub\Core\ValuesFactory\Json;
use Throwable;

/**
 * The full set of rules a schema must pass before a draft-04 revision may be
 * created from it: the safety check, dialect detection, the later-dialect
 * keyword rule and the library-specific checks.
 *
 * Pure: no I/O. The library check is skipped when the safety check fails.
 */
final class SchemaPublicationPolicy
{
    private const DRAFT_04_SCHEMA_ID = 'http://json-schema.org/draft-04/schema#';
    private const DRAFT_04_SCHEMA_ID_WITHOUT_HASH = 'http://json-schema.org/draft-04/schema';

    /** @var list<string> */
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

    public function __construct(
        private readonly SchemaLibraryCheck $libraryCheck,
    ) {
    }

    public function check(Json $schema): SchemaViolations
    {
        $safety = (new SchemaSafetyCheck())->check($schema);
        if (!$safety->isEmpty()) {
            return $safety;
        }

        $document = new SchemaDocument($schema);

        /** @var list<array{int, int, int, SchemaViolation}> */
        $found = [];
        $add = function (SchemaViolation $violation, int $guard) use (&$found, $document): void {
            $found[] = [$document->ranks[$violation->pointer] ?? PHP_INT_MAX, $guard, count($found), $violation];
        };

        if (is_object($document->root) && property_exists($document->root, '$schema')) {
            $declared = $document->root->{'$schema'};
            if (
                !is_string($declared)
                || ($declared !== self::DRAFT_04_SCHEMA_ID
                    && $declared !== self::DRAFT_04_SCHEMA_ID_WITHOUT_HASH)
            ) {
                $add(
                    new SchemaViolation(
                        SchemaViolationCode::UnsupportedDialect,
                        '/$schema',
                        'Unsupported dialect; only draft-04 schemas may be published.',
                    ),
                    7,
                );
            }
        }

        foreach ($document->schemaNodes as [$pointer, $node]) {
            foreach (self::LATER_DIALECT_KEYWORDS as $keyword) {
                if (property_exists($node, $keyword)) {
                    $add(
                        new SchemaViolation(
                            SchemaViolationCode::UnsupportedKeyword,
                            $pointer . '/' . SchemaDocument::escape($keyword),
                            'Keyword "' . $keyword . '" is not supported in draft-04.',
                        ),
                        8,
                    );
                }
            }
        }

        try {
            $library = $this->libraryCheck->check($schema);
        } catch (Throwable $error) {
            $library = new SchemaViolations([
                new SchemaViolation(
                    SchemaViolationCode::InvalidSchema,
                    '',
                    'Schema library check failed: ' . $error->getMessage(),
                ),
            ]);
        }

        foreach ($library->toArray() as $violation) {
            $add($violation, self::guardFor($violation->code));
        }

        $hasSpecific = false;
        foreach ($found as [, , , $violation]) {
            if ($violation->code !== SchemaViolationCode::InvalidSchema || $violation->pointer !== '') {
                $hasSpecific = true;

                break;
            }
        }
        if ($hasSpecific) {
            $found = array_values(array_filter(
                $found,
                static fn (array $entry): bool =>
                    $entry[3]->code !== SchemaViolationCode::InvalidSchema || $entry[3]->pointer !== '',
            ));
        }

        usort(
            $found,
            static fn (array $left, array $right): int =>
                [$left[0], $left[1], $left[2]] <=> [$right[0], $right[1], $right[2]],
        );

        return new SchemaViolations(array_map(static fn (array $entry): SchemaViolation => $entry[3], $found));
    }

    private static function guardFor(SchemaViolationCode $code): int
    {
        return match ($code) {
            SchemaViolationCode::SchemaTooDeep => 0,
            SchemaViolationCode::InvalidKeywordValue => 1,
            SchemaViolationCode::UnsupportedIdentifier => 2,
            SchemaViolationCode::ExternalReference => 3,
            SchemaViolationCode::UnsupportedReference => 4,
            SchemaViolationCode::UnresolvedReference => 5,
            SchemaViolationCode::ReferenceCycle => 6,
            SchemaViolationCode::UnsupportedDialect => 7,
            SchemaViolationCode::UnsupportedKeyword => 8,
            SchemaViolationCode::InvalidPattern => 9,
            SchemaViolationCode::InvalidSchema => 10,
        };
    }
}
