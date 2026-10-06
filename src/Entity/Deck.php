<?php

namespace App\Entity;

use App\Repository\DeckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Колода. Базовая — без владельца, из контента (data/content/decks.yaml), ключ — slug.
 * Колода игрока — с владельцем, slug не нужен.
 */
#[ORM\Entity(repositoryClass: DeckRepository::class)]
#[ORM\Table(name: 'decks')]
#[ORM\UniqueConstraint(name: 'uniq_decks_slug', columns: ['slug'])]
class Deck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Только у базовых колод; у колод игроков — null (уникальность NULL не проверяет)
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $slug = null;

    // null — базовая колода
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    /**
     * @var Collection<int, DeckCard>
     */
    #[ORM\OneToMany(targetEntity: DeckCard::class, mappedBy: 'deck', cascade: ['persist'], orphanRemoval: true)]
    private Collection $cards;

    public function __construct()
    {
        $this->cards = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function isBase(): bool
    {
        return $this->owner === null;
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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, DeckCard>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    /**
     * Заменить состав колоды. Строки уже бывших в колоде карт меняются на месте —
     * иначе Doctrine вставила бы новую строку раньше, чем удалила старую (уникальность deck + card).
     *
     * @param list<array{card: Card, quantity: int}> $cards
     */
    public function replaceCards(array $cards): static
    {
        $quantities = [];
        foreach ($cards as $item) {
            $quantities[spl_object_id($item['card'])] = $item;
        }

        foreach ($this->cards as $deckCard) {
            $item = $quantities[spl_object_id($deckCard->getCard())] ?? null;
            if ($item === null) {
                $this->cards->removeElement($deckCard);
                continue;
            }
            $deckCard->setQuantity($item['quantity']);
            unset($quantities[spl_object_id($deckCard->getCard())]);
        }

        foreach ($quantities as $item) {
            $this->cards->add(new DeckCard($this, $item['card'], $item['quantity']));
        }

        return $this;
    }
}
