<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\SchemaPolicy\SchemaViolations;
use JsonHub\Core\ValuesFactory\Json;

interface SchemaLibraryCheck
{
    public function check(Json $schema): SchemaViolations;
}
