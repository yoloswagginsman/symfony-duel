<?php

namespace App\Service\Content;

use App\Contract\Entity\ReferenceInterface;
use App\Service\Content\Output\ContentImportResult;
use Doctrine\ORM\Event\OnFlushEventArgs;

/**
 * На время импорта раздела слушает onFlush и запоминает созданные и изменённые справочники.
 * Нужен, потому что сервисы (RaceService, TagService, …) сами делают flush:
 * после раздела Doctrine уже не помнит, что менялось.
 */
final class ReferenceChangeCollector
{
    /** @var list<string> */
    private array $created = [];

    /** @var array<string, list<string>> slug => изменившиеся поля */
    private array $updated = [];

    public function onFlush(OnFlushEventArgs $args): void
    {
        $unitOfWork = $args->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof ReferenceInterface) {
                $this->created[] = (string) $entity->getSlug();
            }
        }
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof ReferenceInterface) {
                $this->updated[(string) $entity->getSlug()] = array_keys($unitOfWork->getEntityChangeSet($entity));
            }
        }
    }

    public function addTo(ContentImportResult $result): void
    {
        array_push($result->created, ...$this->created);
        $result->updated += $this->updated;
    }
}
