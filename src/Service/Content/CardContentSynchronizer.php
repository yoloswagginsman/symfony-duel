<?php

namespace App\Service\Content;

use App\Dto\CardYamlDto;
use App\Entity\Card;

/**
 * Переносит карту, созданную, изменённую или удалённую через интерфейс, в контент (cards.yaml).
 * Вызывается из CardService::*WithYaml(). Запись ищется по vendorCode.
 */
readonly class CardContentSynchronizer
{
    public function __construct(
        private ContentStorage $storage,
    ) {
    }

    /**
     * Создание или изменение: заменяет запись карты или добавляет новую в конец.
     */
    public function save(Card $card): void
    {
        $cards = $this->storage->loadCards();
        $index = $this->find($cards, $card);
        $item = CardYamlDto::fromCard($card)->toArray();

        if ($index === null) {
            $cards[] = $item;
        } else {
            $cards[$index] = $item;
        }

        $this->storage->saveCards($cards);
    }

    public function remove(Card $card): void
    {
        $cards = $this->storage->loadCards();
        $index = $this->find($cards, $card);

        if ($index !== null) {
            unset($cards[$index]);
            $this->storage->saveCards($cards);
        }
    }

    /**
     * @param list<array<string, mixed>> $cards
     */
    private function find(array $cards, Card $card): ?int
    {
        foreach ($cards as $i => $item) {
            if (($item['vendorCode'] ?? null) === $card->getVendorCode()) {
                return $i;
            }
        }

        return null;
    }
}
