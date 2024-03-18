<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

interface Entity
{
    public function setSlug(string|null $slug): self;
    public function setData(string $data): self;
    public function getDefinition(): Definition;
    public function setDefinition(Definition $definition): self;
    public function setParent(Entity|null $parent): self;
    public function isPrivate(): bool;
    public function setPrivate(bool $private): self;
    public function getOwner(): User;
    public function setOwner(User $owner): self;
    public function toArray(): array;

//    public function toArray(): array
//    {
//        return [
//            EntityInputField::SLUG->value => $this->slug,
//            EntityInputField::DATA->value => $this->data,
//            EntityInputField::DEFINITION->value => $this->definition,
//            EntityInputField::PARENT->value => $this->parent,
//            EntityInputField::OWNER->value => $this->owner,
//            EntityInputField::PRIVATE->value => $this->private,
//        ];
//    }
}
