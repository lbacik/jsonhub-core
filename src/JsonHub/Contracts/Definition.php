<?php

declare(strict_types=1);

namespace JsonHub\Contracts;

interface Definition
{
    public function setSlug(string | null $slug): self;
    public function getData(): string;
    public function setData(string $data): self;
    public function setParent(Entity $parent): self;
    public function getOwner(): User;
    public function setOwner(User $owner): self;

    public function toArray(): array;

//    public function toArray(): array
//    {
//        return [
//            !isset($this->slug) ?: DefinitionInputField::SLUG->value => $this->slug,
//            DefinitionInputField::DATA->value => $this->data,
//            !isset($this->parent) ?: DefinitionInputField::PARENT->value => $this->parent,
//            DefinitionInputField::OWNER->value => $this->owner,
//        ];
//    }
}
