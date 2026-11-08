<?php

namespace App\Model;

use App\Entity\Card;
use App\Entity\User;
use App\Game\Enum\CardKind;
use App\Game\GameRules;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class CreateDeckModel
{
    private const string LEGENDARY = 'legendary';

    /**
     * @param list<array{card: Card, quantity: int}> $cards
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите название колоды.')]
        public string $name,

        public array $cards = [],

        // null — базовая колода (из контента), иначе — колода игрока
        public ?User $owner = null,

        // Ключ базовой колоды в контенте; у колоды игрока — null
        public ?string $slug = null,

        public ?string $description = null,
    ) {
    }

    #[Assert\Callback]
    public function validateSlug(ExecutionContextInterface $context): void
    {
        if ($this->owner === null && ($this->slug ?? '') === '') {
            $context->buildViolation('Укажите slug базовой колоды.')->atPath('slug')->addViolation();
        }
    }

    /**
     * Правила колоды — из GameRules: размер, копии одной карты, легендарные; постройки в колоду не входят.
     */
    #[Assert\Callback]
    public function validateCards(ExecutionContextInterface $context): void
    {
        $total = 0;
        $seen = [];

        foreach ($this->cards as $item) {
            $card = $item['card'];
            $label = sprintf('%s «%s»', $card->getVendorCode(), $card->getName());
            $total += $item['quantity'];

            if (isset($seen[spl_object_id($card)])) {
                $context->buildViolation(sprintf('%s указана дважды — объедините в одну строку.', $label))->atPath('cards')->addViolation();
            }
            $seen[spl_object_id($card)] = true;

            if ($card->getCardType()?->getSlug() === CardKind::Building->value) {
                $context->buildViolation(sprintf('%s — постройка, в колоду не входит.', $label))->atPath('cards')->addViolation();
            }

            $maxCopies = $card->getRarity()?->getSlug() === self::LEGENDARY ? GameRules::MAX_LEGENDARY_COPIES : GameRules::MAX_COPIES;
            if ($item['quantity'] < 1 || $item['quantity'] > $maxCopies) {
                $context->buildViolation(sprintf('%s: копий %d, можно от 1 до %d.', $label, $item['quantity'], $maxCopies))->atPath('cards')->addViolation();
            }
        }

        if ($total !== GameRules::DECK_SIZE) {
            $context->buildViolation(sprintf('В колоде %d карт, нужно ровно %d.', $total, GameRules::DECK_SIZE))->atPath('cards')->addViolation();
        }
    }
}
