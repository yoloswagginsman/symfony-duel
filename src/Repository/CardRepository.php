<?php

namespace App\Repository;

use App\Entity\Card;
use App\Game\Enum\CardKind;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends AbstractRepository<Card>
 */
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

    /**
     * Карты, которые можно положить в колоду: все, кроме построек (они попадают на точки сами).
     * По мане, затем по названию — как в конструкторе колод.
     *
     * @return list<Card>
     */
    public function findDeckPool(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.cardType', 't')
            ->addSelect('t')
            ->leftJoin('c.race', 'r')
            ->addSelect('r')
            ->leftJoin('c.rarity', 'y')
            ->addSelect('y')
            ->where('t.slug != :building')
            ->setParameter('building', CardKind::Building->value)
            ->orderBy('c.manaCost', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Карты одного типа, например нейтральные постройки для точек сопряжения ('building').
     *
     * @return list<Card>
     */
    public function findByCardTypeSlug(string $typeSlug): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.cardType', 't')
            ->where('t.slug = :slug')
            ->setParameter('slug', $typeSlug)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
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
