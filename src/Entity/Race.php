<?php

namespace App\Entity;

use App\Repository\RaceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RaceRepository::class)]
#[ORM\Table(name: 'races')]
class Race
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private ?string $slug = null;

    /**
     * @var Collection<int, Card>
     */
    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'race')]
    private Collection $raceCards;

    public function __construct()
    {
        $this->raceCards = new ArrayCollection();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    /**
     * @return Collection<int, Card>
     */
    public function getRaceCards(): Collection
    {
        return $this->raceCards;
    }

    public function addRaceCard(Card $raceCard): static
    {
        if (!$this->raceCards->contains($raceCard)) {
            $this->raceCards->add($raceCard);
            $raceCard->setRace($this);
        }

        return $this;
    }

    public function removeRaceCard(Card $raceCard): static
    {
        if ($this->raceCards->removeElement($raceCard)) {
            // set the owning side to null (unless already changed)
            if ($raceCard->getRace() === $this) {
                $raceCard->setRace(null);
            }
        }

        return $this;
    }
}
