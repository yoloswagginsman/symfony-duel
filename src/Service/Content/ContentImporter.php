<?php

namespace App\Service\Content;

use App\Contract\DataContent\DataContentInterface;
use App\Contract\DataContent\DeleteMissingInterface;
use App\Service\Content\Output\ContentImportResult;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

/**
 * Загружает раздел контента (data/content) в базу — в своей транзакции.
 * Если в разделе ошибки или включён dry-run, транзакция откатывается:
 * база остаётся как была, а результат показывает, что было бы сделано.
 */
readonly class ContentImporter
{
    public function __construct(
        #[AutowireLocator(DataContentInterface::class)]
        private ContainerInterface $dataContents,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param class-string<DataContentInterface> $class раздел контента
     * @param bool $dryRun только показать, что изменится
     * @param bool $deleteMissing удалить записи, которых нет в контенте (если раздел это умеет)
     * @throws Exception|Throwable
     */
    public function import(string $class, bool $dryRun = false, bool $deleteMissing = false): ContentImportResult
    {
        $dataContent = $this->dataContent($class);
        $result = new ContentImportResult($dataContent->getName());
        $changes = new ReferenceChangeCollector();
        $eventManager = $this->entityManager->getEventManager();
        $eventManager->addEventListener(Events::onFlush, $changes);

        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();

        try {
            try {
                $dataContent->import($result);
            } catch (ValidationFailedException $e) {
                // Справочник не прошёл проверку модели в сервисе
                foreach ($e->getViolations() as $violation) {
                    $result->errors[] = sprintf('«%s»: %s — %s', $e->getValue()->slug ?? '?', $violation->getPropertyPath(), $violation->getMessage());
                }
            }
            $changes->addTo($result);

            if ($deleteMissing && $dataContent instanceof DeleteMissingInterface && !$result->hasErrors()) {
                $this->deleteMissing($dataContent, $result, $dryRun);
            }

            if ($dryRun || $result->hasErrors()) {
                $connection->rollBack();
                $this->entityManager->clear();
            } else {
                $connection->commit();
            }
        } catch (Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            $this->entityManager->clear();

            throw $e;
        } finally {
            $eventManager->removeEventListener(Events::onFlush, $changes);
        }

        return $result;
    }

    /**
     * В dry-run только показываем, что будет удалено: вместе с записью удаляются файлы (картинки),
     * а их откат транзакции не вернёт.
     */
    private function deleteMissing(DeleteMissingInterface $dataContent, ContentImportResult $result, bool $dryRun): void
    {
        if ($dryRun) {
            $result->deleted = $result->missing;
            $result->missing = [];

            return;
        }

        $dataContent->deleteMissing($result);
    }

    private function dataContent(string $class): DataContentInterface
    {
        if (!$this->dataContents->has($class)) {
            throw new ContentException(sprintf('Раздел %s не найден: он должен реализовывать %s', $class, DataContentInterface::class));
        }

        return $this->dataContents->get($class);
    }
}
