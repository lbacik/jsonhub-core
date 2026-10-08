<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\CanonicalJson\CanonicalJson;
use JsonHub\Core\CanonicalJson\SchemaCharge;
use JsonHub\Core\SchemaPolicy\Dialect;
use JsonHub\Core\Versioning\RevisionNumber;

interface DefinitionRevision
{
    public function getId(): string;
    public function getDefinition(): Definition;
    public function getNumber(): RevisionNumber;
    public function getDialect(): Dialect;
    public function getSchema(): CanonicalJson;
    public function getSchemaCharge(): SchemaCharge;
}
