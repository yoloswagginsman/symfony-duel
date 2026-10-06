<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GameMove;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GameMove>
 */
class GameMoveRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GameMove::class);
    }

    /**
     * Последние ходы партии — по порядку (старые первыми).
     *
     * @return list<GameMove>
     */
    public function findLastOf(Game $game, int $limit): array
    {
        $moves = $this->createQueryBuilder('m')
            ->andWhere('m.game = :game')
            ->setParameter('game', $game)
            ->orderBy('m.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_reverse($moves);
    }
}
