<?php

namespace App\Repository;

use App\Entity\Card;
use Doctrine\Persistence\ManagerRegistry;

class CardRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Card::class);
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

    public function countByRarity(): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('r.name, COUNT(c.id) as count')
            ->join('c.rarity', 'r')
            ->groupBy('r.id')
            ->orderBy('count', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function findByRace(string $raceSlug): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.race', 'r')
            ->where('r.slug = :slug')
            ->setParameter('slug', $raceSlug)
            ->getQuery()
            ->getResult();
    }

    /**
     * Сохраняем в БД карту
     *
     * @param Card $card
     * @return int
     */
    public function create(Card $card): int
    {
        return $this->store($card);
    }
}
