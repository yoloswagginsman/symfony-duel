<?php

namespace App\DataContent;

use App\Contract\DataContent\DataContentInterface;
use App\Dto\DeckYamlDto;
use App\Dto\Factory\DeckYamlDtoFactory;
use App\Repository\DeckRepository;
use App\Service\Content\ChangeSetCalculator;
use App\Service\Content\ContentException;
use App\Service\Content\ContentStorage;
use App\Service\Content\Output\ContentImportResult;
use App\Service\DeckService;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Базовые колоды из data/content/decks.yaml, сопоставление по slug. Колоды игроков импорт не трогает.
 * Как и у карт, созданные и обновлённые раздел отмечает в результате сам.
 */
readonly class DeckYaml implements DataContentInterface
{
    public function __construct(
        private ContentStorage $storage,
        private DeckYamlDtoFactory $dtoFactory,
        private ChangeSetCalculator $changeSetCalculator,
        private DeckRepository $deckRepository,
        private DeckService $deckService,
    ) {
    }

    public function getName(): string
    {
        return 'decks';
    }

    public function import(ContentImportResult $result): void
    {
        foreach ($this->storage->loadDecks() as $position => $item) {
            $label = sprintf('#%d «%s»', $position + 1, $item['name'] ?? '?');

            try {
                $dto = $this->dtoFactory->fromArray($item);
            } catch (ContentException $e) {
                $result->errors[] = sprintf('%s: %s', $label, $e->getMessage());
                continue;
            } catch (\TypeError) {
                $result->errors[] = sprintf('%s: у поля неверный тип (число вместо строки или наоборот)', $label);
                continue;
            }

            $deck = $this->deckRepository->findOneBy(['slug' => $dto->slug, 'owner' => null]);
            $record = sprintf('%s «%s»', $dto->slug, $dto->name);

            try {
                if ($deck === null) {
                    $this->deckService->create($dto->toModel());
                    $result->created[] = $record;
                    continue;
                }

                $changes = $this->changeSetCalculator->calculate(
                    DeckYamlDto::fromDeck($deck)->toArray(),
                    $dto->toArray(),
                    ignore: ['slug'],   // ключ записи
                );
                if ($changes !== []) {
                    $this->deckService->update($deck, $dto->toModel());
                    $result->updated[$record] = $changes;
                }
            } catch (ValidationFailedException $e) {
                foreach ($e->getViolations() as $violation) {
                    $result->errors[] = sprintf('%s: %s', $label, $violation->getMessage());
                }
            }
        }
    }
}
