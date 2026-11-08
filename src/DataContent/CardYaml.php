<?php

namespace App\DataContent;

use App\Contract\DataContent\DataContentInterface;
use App\Contract\DataContent\DeleteMissingInterface;
use App\Dto\CardYamlDto;
use App\Dto\Factory\CardYamlDtoFactory;
use App\Entity\Card;
use App\Repository\CardRepository;
use App\Service\CardService;
use App\Service\Content\ChangeSetCalculator;
use App\Service\Content\ContentException;
use App\Service\Content\ContentStorage;
use App\Service\Content\Output\ContentImportResult;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Карты из data/content/cards/<тип>/<раса>.yaml. Сопоставление с базой — по vendorCode.
 *
 * Карты сохраняются через CardService: он же проверяет модель. Если хоть одна карта
 * не прошла — раздел в ошибках, и ContentImporter откатывает его целиком.
 * Карты CardService записывает сам (flush), поэтому созданные и обновлённые
 * раздел отмечает в результате сам.
 */
readonly class CardYaml implements DataContentInterface, DeleteMissingInterface
{
    public function __construct(
        private ContentStorage $storage,
        private CardYamlDtoFactory $dtoFactory,
        private ChangeSetCalculator $changeSetCalculator,
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
        $seenCodes = [];
        $seenNames = [];

        foreach ($this->storage->loadCardFiles() as $file => $items) {
            foreach ($items as $position => $item) {
                $this->importCard($result, sprintf('%s #%d «%s»', $file, $position + 1, $item['name'] ?? '?'), $item, $seenCodes, $seenNames);
            }
        }

        // Карты, которых нет в контенте, только отмечаем — удалит deleteMissing() (--delete-missing)
        foreach ($this->missingCards() as $card) {
            $result->missing[] = $this->label($card);
        }
    }

    /**
     * Удаляет карты, которых нет в контенте, — с картинкой (CardService::delete).
     */
    public function deleteMissing(ContentImportResult $result): void
    {
        foreach ($this->missingCards() as $card) {
            $this->cardService->delete($card);
            $result->deleted[] = $this->label($card);
        }
        $result->missing = [];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, true>  $seenCodes
     * @param array<string, true>  $seenNames
     */
    private function importCard(ContentImportResult $result, string $label, array $item, array &$seenCodes, array &$seenNames): void
    {
        try {
            $dto = $this->dtoFactory->fromArray($item);
        } catch (ContentException $e) {
            $result->errors[] = sprintf('%s: %s', $label, $e->getMessage());
            return;
        } catch (\TypeError) {
            $result->errors[] = sprintf('%s: у поля неверный тип (число вместо строки или наоборот)', $label);
            return;
        }

        // Дубли в самом контенте — ошибка: непонятно, какая запись правильная
        if (isset($seenCodes[$dto->vendorCode])) {
            $result->errors[] = sprintf('%s: повторяется vendorCode %s', $label, $dto->vendorCode);
            return;
        }
        if (isset($seenNames[$dto->name])) {
            $result->errors[] = sprintf('%s: повторяется название', $label);
            return;
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
                $this->cardService->create($dto->toModel());
                $result->created[] = $record;
                return;
            }

            $changes = $this->changeSetCalculator->calculate(
                CardYamlDto::fromCard($card)->toArray(),
                $dto->toArray(),
                ignore: ['vendorCode'],   // ключ записи
            );
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

    /**
     * Карты в базе, артикулов которых нет в контенте. Запись с ошибкой — не «нет в контенте»:
     * сверяем по артикулам из файла, а не по успешно импортированным картам.
     *
     * @return list<Card>
     */
    private function missingCards(): array
    {
        $codesInContent = array_column($this->storage->loadCards(), 'vendorCode');

        return array_values(array_filter(
            $this->cardRepository->findAll(),
            static fn (Card $card) => !in_array($card->getVendorCode(), $codesInContent, true),
        ));
    }

    private function label(Card $card): string
    {
        return sprintf('%s «%s»', $card->getVendorCode(), $card->getName());
    }
}
