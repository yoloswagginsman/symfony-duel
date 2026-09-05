<?php

namespace App\Entity;

use App\Repository\RaritiesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RaritiesRepository::class)]
#[ORM\Table(name: 'rarities')]
class Rarity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $name = null;

    #[ORM\Column(length: 7)]
    private ?string $colorHex = null;

    #[ORM\Column]
    private ?int $dropChance = null;

    #[ORM\Column(length: 50)]
    private ?string $slug = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getColorHex(): ?string
    {
        return $this->colorHex;
    }

    public function setColorHex(string $colorHex): static
    {
        $this->colorHex = $colorHex;

        return $this;
    }

    public function getDropChance(): ?int
    {
        return $this->dropChance;
    }

    public function setDropChance(int $dropChance): static
    {
        $this->dropChance = $dropChance;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }
}
