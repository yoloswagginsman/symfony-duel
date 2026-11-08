<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * Партии других игроков, к которым можно присоединиться.
     *
     * @return list<Game>
     */
    public function findWaitingFor(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.status = :status')
            ->andWhere('g.player0 != :user')
            ->setParameter('status', GameStatus::WAITING)
            ->setParameter('user', $user)
            ->orderBy('g.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Незавершённые партии пользователя: ждут соперника или идут.
     *
     * @return list<Game>
     */
    public function findUnfinishedOf(User $user): array
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.status != :finished')
            ->andWhere('g.player0 = :user OR g.player1 = :user')
            ->setParameter('finished', GameStatus::FINISHED)
            ->setParameter('user', $user)
            ->orderBy('g.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
