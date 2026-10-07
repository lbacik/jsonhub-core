<?php

declare(strict_types=1);

namespace JsonHub\Core\SchemaPolicy;

enum SchemaViolationCode: string
{
    case UnsupportedDialect = 'unsupported_dialect';
    case UnsupportedKeyword = 'unsupported_keyword';
    case UnsupportedIdentifier = 'unsupported_identifier';
    case ExternalReference = 'external_reference';
    case UnsupportedReference = 'unsupported_reference';
    case UnresolvedReference = 'unresolved_reference';
    case ReferenceCycle = 'reference_cycle';
    case SchemaTooDeep = 'schema_too_deep';
    case InvalidKeywordValue = 'invalid_keyword_value';
    case InvalidPattern = 'invalid_pattern';
    case InvalidSchema = 'invalid_schema';
}
