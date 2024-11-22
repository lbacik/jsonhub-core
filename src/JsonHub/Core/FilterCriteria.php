<?php

declare(strict_types=1);

namespace JsonHub\Core;

use Sushi\ValueObject;
use Sushi\ValueObject\Invariant;

class FilterCriteria extends ValueObject
{
    public function __construct(
        public string|null $searchString = null,
        public string|null $slug = null,
        public string|null $definition = null,
        public string|false|null $parent = null,
        public string|null $owner = null,
        public bool|null $private = null,
        public int $offset = 0,
        public int $limit = 10,
    ) {
        parent::__construct();
    }

    #[Invariant]
    public function accessToPrivateItems(): void
    {
        if ($this->private && !$this->owner) {
            throw new \InvalidArgumentException('Owner is required to get private entities');
        }
    }
}
