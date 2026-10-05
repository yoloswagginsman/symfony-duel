<?php

namespace App\Repository;

use App\Contract\Entity\EntityInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @template T of EntityInterface
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractRepository extends ServiceEntityRepository
{
    /**
     * @param class-string<T> $entityClass
     */
    public function __construct(ManagerRegistry $registry, string $entityClass)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * Сохраняет сущность и мгновенно выполняет flush.
     *
     * @param T $entity
     */
    public function store(EntityInterface $entity, bool $flush = true): int
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->flush();
        }

        return $entity->getId();
    }

    /**
     * Удаляет сущность.
     *
     * @param T $entity
     */
    public function remove(EntityInterface $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->flush();
        }
    }

    /**
     * Обновляет состояние сущности из базы данных.
     *
     * @param T $entity
     */
    public function refresh(EntityInterface $entity): void
    {
        $this->getEntityManager()->refresh($entity);
    }

    /**
     * Принудительный сброс изменений в БД.
     */
    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}