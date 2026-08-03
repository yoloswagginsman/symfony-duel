<?php

namespace App\Repository;

use App\Entity\Cards;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cards>
 */
class CardsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cards::class);
    }

    /**
     * Найти карты по редкости (по slug)
     * Использует: idx_cards_rarity
     */
    public function findByRarity(string $raritySlug): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.rarity', 'r')
            ->andWhere('r.slug = :raritySlug')
            ->setParameter('raritySlug', $raritySlug)
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.manaCost', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
