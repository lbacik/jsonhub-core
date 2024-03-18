<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\ValuesFactory\Json;

interface JsonSchemaValidator
{
    public function isValid(Json $schema): bool;
    public function getError(): string;
}
