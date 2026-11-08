<?php

namespace App\Contract\Entity;

/**
 * Справочник контента (раса, тип, редкость, тег, способность):
 * запись с постоянным ключом slug, по которому её находят импорт и карты в YAML.
 */
interface ReferenceInterface extends EntityInterface
{
    public function getSlug(): ?string;

    public function setSlug(string $slug): static;

    public function getName(): ?string;

    public function setName(string $name): static;
}
