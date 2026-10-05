<?php

namespace App\Entity;

use App\Contract\Entity\ArchiveInterface;
use App\Contract\Entity\EntityInterface;
use App\Repository\CardRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: CardRepository::class)]
#[ORM\Table(name: 'cards')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_cards_race', columns: ['race_id'])]
#[ORM\Index(name: 'idx_cards_type', columns: ['card_type_id'])]
#[ORM\Index(name: 'idx_cards_rarity', columns: ['rarity_id'])]
#[ORM\Index(name: 'idx_cards_race_type', columns: ['race_id', 'card_type_id'])]
#[ORM\Index(name: 'idx_cards_mana_type', columns: ['mana_cost', 'card_type_id'])]
#[ORM\Index(name: 'idx_cards_archived_at', columns: ['archived_at'])]
#[ORM\UniqueConstraint(name: 'uniq_cards_name', columns: ['name'])]
#[ORM\UniqueConstraint(name: 'uniq_cards_vendor_code', columns: ['vendor_code'])]
#[UniqueEntity(fields: ['name'], message: 'Карта с таким названием уже существует.')]
#[UniqueEntity(fields: ['vendorCode'], message: 'Карта с таким артикулом уже существует.')]
class Card implements EntityInterface, ArchiveInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?int $manaCost;

    #[ORM\Column(nullable: true)]
    private ?int $attack = null;

    #[ORM\Column(nullable: true)]
    private ?int $health = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $archivedAt = null;

    #[ORM\Column]
    private ?bool $isActive;

    #[ORM\Column]
    private ?\DateTime $createdAt;

    #[ORM\Column]
    private ?\DateTime $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'cards')]
    #[ORM\JoinTable(name: 'card_tags')]
    #[ORM\JoinColumn(name: 'card_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    private Collection $tags;

    #[ORM\ManyToOne(inversedBy: 'raceCards')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Race $race = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CardType $cardType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Rarity $rarity = null;

    /**
     * @var Collection<int, CardAbility>
     */
    #[ORM\OneToMany(targetEntity: CardAbility::class, mappedBy: 'card', cascade: ['persist'], orphanRemoval: true)]
    private Collection $abilities;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $vendorCode = null;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->abilities = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->isActive = false;
        $this->manaCost = 0;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $now = new \DateTime();

        $this->createdAt ??= $now;
        $this->updatedAt = $now;
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

    public function getVendorCode(): ?string
    {
        return $this->vendorCode;
    }

    public function setVendorCode(?string $vendorCode): self
    {
        $this->vendorCode = $vendorCode;
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

    public function getManaCost(): ?int
    {
        return $this->manaCost;
    }

    public function setManaCost(int $manaCost): static
    {
        $this->manaCost = $manaCost;

        return $this;
    }

    public function getAttack(): ?int
    {
        return $this->attack;
    }

    public function setAttack(?int $attack): static
    {
        $this->attack = $attack;

        return $this;
    }

    public function getHealth(): ?int
    {
        return $this->health;
    }

    public function setHealth(?int $health): static
    {
        $this->health = $health;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getRace(): ?Race
    {
        return $this->race;
    }

    public function setRace(?Race $race): static
    {
        $this->race = $race;

        return $this;
    }

    public function getCardType(): ?CardType
    {
        return $this->cardType;
    }

    public function setCardType(?CardType $cardType): static
    {
        $this->cardType = $cardType;

        return $this;
    }

    public function getRarity(): ?Rarity
    {
        return $this->rarity;
    }

    public function setRarity(?Rarity $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
    }

    public function getTags(): Collection
    {
        return $this->tags;
    }

    /**
     * @param Collection<int, Tag>|array<int, Tag> $tags
     */
    public function setTags(Collection|array $tags): static
    {
        $newTags = $tags instanceof Collection ? $tags->toArray() : (array) $tags;

        $newTagIds = [];
        foreach ($newTags as $tag) {
            if ($tag instanceof Tag && $tag->getId() !== null) {
                $newTagIds[$tag->getId()] = $tag;
            }
        }

        foreach ($this->tags as $existingTag) {
            if (!isset($newTagIds[$existingTag->getId()])) {
                $this->removeTag($existingTag);
            }
        }

        foreach ($newTags as $tag) {
            if ($tag instanceof Tag) {
                $this->addTag($tag);
            }
        }

        return $this;
    }

    public function addTag(Tag $tag): static
    {
        foreach ($this->tags as $existingTag) {
            if ($existingTag->getId() === $tag->getId()) {
                return $this;
            }
        }

        $this->tags->add($tag);

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        foreach ($this->tags as $key => $existingTag) {
            if ($existingTag->getId() === $tag->getId()) {
                $this->tags->remove($key);
                break;
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CardAbility>
     */
    public function getAbilities(): Collection
    {
        return $this->abilities;
    }

    public function addAbility(Ability $ability): static
    {
        return $this->addAbilityWithValue($ability, null);
    }

    public function addCardAbility(CardAbility $ability): static
    {
        if (!$this->abilities->contains($ability)) {
            $this->abilities->add($ability);
            $ability->setCard($this);
        }

        return $this;
    }

    public function removeCardAbility(CardAbility $ability): static
    {
        if ($this->abilities->removeElement($ability)) {
            if ($ability->getCard() === $this) {
                $ability->setCard(null);
            }
        }

        return $this;
    }

    public function clearAbilities(): static
    {
        $this->abilities->clear();

        return $this;
    }

    public function addAbilityWithValue(Ability $ability, ?int $value = null): static
    {
        foreach ($this->abilities as $existingCardAbility) {
            if ($existingCardAbility->getAbility() === $ability) {
                $existingCardAbility->setValue($value);
                return $this;
            }
        }

        $cardAbility = new CardAbility();
        $cardAbility->setAbility($ability);
        $cardAbility->setValue($value);

        return $this->addCardAbility($cardAbility);
    }

    /**
     * Удобный метод для получения значения способности
     */
    public function getAbilityValue(Ability $ability): ?int
    {
        foreach ($this->abilities as $cardAbility) {
            if ($cardAbility->getAbility() === $ability) {
                return $cardAbility->getValue();
            }
        }

        return null;
    }

    /**
     * Удобный метод для удаления способности
     */
    public function removeAbilityByName(Ability $ability): static
    {
        foreach ($this->abilities as $cardAbility) {
            if ($cardAbility->getAbility() === $ability) {
                $this->abilities->removeElement($cardAbility);
                $cardAbility->setCard(null);
                break;
            }
        }

        return $this;
    }

    /**
     * Проверяет, есть ли у карты определённая способность
     */
    public function hasAbility(Ability $ability): bool
    {
        foreach ($this->abilities as $cardAbility) {
            if ($cardAbility->getAbility() === $ability) {
                return true;
            }
        }

        return false;
    }

    /**
     * Получить все способности в виде ассоциативного массива [название => значение]
     */
    public function getAbilitiesWithValues(): array
    {
        $result = [];
        foreach ($this->abilities as $cardAbility) {
            $result[$cardAbility->getAbility()->getName()] = $cardAbility->getValue();
        }

        return $result;
    }

    public function getArchivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function setArchivedAt(?\DateTimeInterface $archivedAt): static
    {
        if ($archivedAt instanceof \DateTime) {
            $archivedAt = \DateTimeImmutable::createFromMutable($archivedAt);
        }

        $this->archivedAt = $archivedAt;

        return $this;
    }

    /**
     * Вспомогательный метод: проверяет, заархивирована ли карта
     */
    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    /**
     * Вспомогательный метод: отправляет карту в архив
     */
    public function archive(): static
    {
        $this->archivedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Вспомогательный метод: достает карту из архива
     */
    public function unarchive(): static
    {
        $this->archivedAt = null;

        return $this;
    }
}
