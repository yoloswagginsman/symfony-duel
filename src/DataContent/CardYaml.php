<?php

namespace App\DataContent;

use App\Contract\DataContent\DataContentInterface;
use App\Dto\CardYamlDto;
use App\Dto\Factory\CardYamlDtoFactory;
use App\Repository\CardRepository;
use App\Service\CardService;
use App\Service\Content\ContentException;
use App\Service\Content\ContentStorage;
use App\Service\Content\Output\ContentImportResult;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Карты из data/content/cards.yaml. Сопоставление с базой — по vendorCode.
 *
 * Карты сохраняются через CardService: он же проверяет модель. Если хоть одна карта
 * не прошла — раздел в ошибках, и ContentImporter откатывает его целиком.
 * Карты CardService записывает сам (flush), поэтому созданные и обновлённые
 * раздел отмечает в результате сам.
 */
readonly class CardYaml implements DataContentInterface
{
    public function __construct(
        private ContentStorage $storage,
        private CardYamlDtoFactory $dtoFactory,
        private CardRepository $cardRepository,
        private CardService $cardService,
        private string $uploadsDirectory,
    ) {
    }

    public function getName(): string
    {
        return 'cards';
    }

    public function import(ContentImportResult $result): void
    {
        $matchedIds = [];
        $seenCodes = [];
        $seenNames = [];

        foreach ($this->storage->loadCards() as $position => $item) {
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

            // Дубли в самом контенте — ошибка: непонятно, какая запись правильная
            if (isset($seenCodes[$dto->vendorCode])) {
                $result->errors[] = sprintf('%s: повторяется vendorCode %s', $label, $dto->vendorCode);
                continue;
            }
            if (isset($seenNames[$dto->name])) {
                $result->errors[] = sprintf('%s: повторяется название', $label);
                continue;
            }
            $seenCodes[$dto->vendorCode] = true;
            $seenNames[$dto->name] = true;

            if ($dto->imagePath !== null && !is_file($this->uploadsDirectory . '/' . $dto->imagePath)) {
                $result->warnings[] = sprintf('%s: нет файла картинки %s', $label, $dto->imagePath);
            }

            $card = $this->cardRepository->findOneBy(['vendorCode' => $dto->vendorCode]);
            $record = sprintf('%s «%s»', $dto->vendorCode, $dto->name);

            try {
                if ($card === null) {
                    $matchedIds[] = $this->cardService->create($dto->toModel())->getId();
                    $result->created[] = $record;
                    continue;
                }

                $matchedIds[] = $card->getId();
                $changes = CardYamlDto::fromCard($card)->changedFields($dto);
                if ($changes !== []) {
                    $this->cardService->update($card, $dto->toModel());
                    $result->updated[$record] = $changes;
                }
            } catch (ValidationFailedException $e) {
                foreach ($e->getViolations() as $violation) {
                    $result->errors[] = sprintf('%s: %s — %s', $label, $violation->getPropertyPath(), $violation->getMessage());
                }
            }
        }

        // Карты, которых нет в контенте, не удаляем — только сообщаем
        foreach ($this->cardRepository->findAll() as $card) {
            if (!in_array($card->getId(), $matchedIds, true)) {
                $result->missing[] = sprintf('%s «%s»', $card->getVendorCode(), $card->getName());
            }
        }
    }
}
