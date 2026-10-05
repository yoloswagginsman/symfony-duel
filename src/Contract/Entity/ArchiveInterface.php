<?php

namespace App\Contract\Entity;

interface ArchiveInterface
{
    public function getArchivedAt(): ?\DateTimeImmutable;

    public function setArchivedAt(?\DateTimeInterface $archivedAt): static;

    public function isArchived(): bool;

    public function archive(): static;

    public function unarchive(): static;
}