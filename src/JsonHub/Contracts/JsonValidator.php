<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

use JsonHub\Core\ValuesFactory\Json;

interface JsonValidator
{
    public function isValid(Json $json, Json $schema): bool;
    public function getError(): string;
}
