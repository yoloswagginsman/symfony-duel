<?php

namespace App\Dto\Factory;

use App\Dto\DeckYamlDto;
use App\Repository\CardRepository;
use App\Service\Content\ContentException;

/**
 * Запись из data/content/decks.yaml → DeckYamlDto: карты ищутся по vendorCode.
 */
readonly class DeckYamlDtoFactory
{
    public function __construct(
        private CardRepository $cardRepository,
    ) {
    }

    /**
     * Неизвестная карта — ошибка, чтобы колода не собралась молча без неё.
     *
     * @param array<string, mixed> $item
     *
     * @throws ContentException
     * @throws \TypeError если у поля неверный тип
     */
    public function fromArray(array $item): DeckYamlDto
    {
        return new DeckYamlDto(
            slug: $item['slug'] ?? throw new ContentException('нет slug'),
            name: $item['name'] ?? throw new ContentException('нет названия'),
            description: ($item['description'] ?? null) ?: null,
            cards: array_map(
                fn (array $row) => [
                    'card' => $this->cardRepository->findOneBy(['vendorCode' => $row['card'] ?? null])
                        ?? throw new ContentException(sprintf('неизвестная карта "%s"', $row['card'] ?? '?')),
                    'quantity' => $row['count'] ?? 1,
                ],
                $item['cards'] ?? [],
            ),
        );
    }
}
