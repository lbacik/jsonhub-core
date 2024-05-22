<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

interface Definition
{
    public function getId(): string;
    public function setSlug(string | null $slug): self;
    public function getData(): string;
    public function setData(string $data): self;
    public function getParent(): Entity|null;
    public function setParent(Entity $parent): self;
    public function getOwner(): User|null;
    public function setOwner(User $owner): self;

    public function toArray(): array;
}
