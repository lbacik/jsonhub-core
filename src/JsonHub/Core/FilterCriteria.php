<?php

declare(strict_types=1);

namespace JsonHub\Core;

class FilterCriteria
{
    public function __construct(
        public string | null $slug = null,
        public string | null $definition = null,
        public string | false | null $parent = null,
        public string | null $owner = null,
        public bool | null $private = null,
        public int $offset = 0,
        public int $limit = 10,
    ) {
    }
}
