<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\Types\Json;

interface JsonSchemaValidator
{
    public function isValid(Json $schema): bool;
    public function getError(): string;
}
