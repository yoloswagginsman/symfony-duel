<?php

namespace App\Entity;

use App\Repository\CardAbilitiesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CardAbilitiesRepository::class)]
class CardAbilities
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'abilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Cards $card = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Abilities $ability = null;

    #[ORM\Column(nullable: true)]
    private ?int $value = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAbility(): ?Abilities
    {
        return $this->ability;
    }

    public function setAbility(?Abilities $ability): static
    {
        $this->ability = $ability;

        return $this;
    }

    public function getValue(): ?int
    {
        return $this->value;
    }

    public function setValue(?int $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getCard(): ?Cards
    {
        return $this->card;
    }

    public function setCard(?Cards $card): static
    {
        $this->card = $card;

        return $this;
    }
}
