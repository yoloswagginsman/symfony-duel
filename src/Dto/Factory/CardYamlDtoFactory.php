<?php

namespace App\Dto\Factory;

use App\Dto\CardYamlDto;
use App\Repository\AbilityRepository;
use App\Repository\CardTypeRepository;
use App\Repository\RaceRepository;
use App\Repository\RarityRepository;
use App\Repository\TagRepository;
use App\Service\Content\ContentException;

/**
 * Запись из файла карт (data/content/cards/…) → CardYamlDto: справочники ищутся по slug, способности — по имени.
 */
readonly class CardYamlDtoFactory
{
    public function __construct(
        private RaceRepository $raceRepository,
        private CardTypeRepository $cardTypeRepository,
        private RarityRepository $rarityRepository,
        private TagRepository $tagRepository,
        private AbilityRepository $abilityRepository,
    ) {
    }

    /**
     * Неизвестная ссылка — ошибка, чтобы импорт не потерял данные молча.
     * Без названия и артикула запись не принимается (по ним ищутся дубли и карта в базе);
     * остальные пропущенные поля остаются null — их отклонит валидация модели в CardService.
     *
     * @param array<string, mixed> $item
     *
     * @throws ContentException
     * @throws \TypeError если у поля неверный тип (например, "manaCost: пять")
     */
    public function fromArray(array $item): CardYamlDto
    {
        return new CardYamlDto(
            name: $item['name'] ?? throw new ContentException('нет названия'),
            manaCost: $item['manaCost'] ?? null,
            race: isset($item['race'])
                ? $this->raceRepository->findOneBy(['slug' => $item['race']])
                    ?? throw new ContentException(sprintf('неизвестная раса "%s"', $item['race']))
                : null,
            cardType: isset($item['type'])
                ? $this->cardTypeRepository->findOneBy(['slug' => $item['type']])
                    ?? throw new ContentException(sprintf('неизвестный тип "%s"', $item['type']))
                : null,
            rarity: isset($item['rarity'])
                ? $this->rarityRepository->findOneBy(['slug' => $item['rarity']])
                    ?? throw new ContentException(sprintf('неизвестная редкость "%s"', $item['rarity']))
                : null,
            // По артикулу карта сопоставляется с базой — без него запись не принимаем
            vendorCode: $item['vendorCode'] ?? throw new ContentException('нет vendorCode'),
            description: ($item['description'] ?? null) ?: null,
            imagePath: $item['imagePath'] ?? null,
            attack: $item['attack'] ?? null,
            health: $item['health'] ?? null,
            isActive: $item['isActive'] ?? false,
            tags: array_map(
                fn (string $slug) => $this->tagRepository->findOneBy(['slug' => $slug])
                    ?? throw new ContentException(sprintf('неизвестный тег "%s"', $slug)),
                $item['tags'] ?? [],
            ),
            abilities: array_map(function (string|array $ability) {
                $name = is_array($ability) ? ($ability['name'] ?? '') : $ability;

                return [
                    'ability' => $this->abilityRepository->findOneBy(['name' => $name])
                        ?? throw new ContentException(sprintf('неизвестная способность "%s"', $name)),
                    'value' => is_array($ability) ? ($ability['value'] ?? null) : null,
                ];
            }, $item['abilities'] ?? []),
        );
    }
}
