<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\CanonicalJson\CanonicalJson;
use JsonHub\Core\CanonicalJson\SchemaCharge;

interface DefinitionDraft
{
    public function getDefinition(): Definition;
    public function getSchema(): CanonicalJson;
    public function getBaseRevision(): DefinitionRevision;
    public function getVersion(): int;
    public function getSchemaCharge(): SchemaCharge;
}
