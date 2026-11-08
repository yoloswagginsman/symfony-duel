<?php

namespace App\Repository;

use App\Entity\Deck;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Deck>
 */
class DeckRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Deck::class);
    }

    /**
     * Колоды, которыми может играть пользователь: базовые и свои.
     *
     * @return list<Deck>
     */
    public function findAvailableFor(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.owner IS NULL OR d.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('d.owner', 'DESC')
            ->addOrderBy('d.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
