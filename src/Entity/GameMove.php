<?php

namespace App\Entity;

use App\Repository\GameMoveRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ход партии — одно действие (розыгрыш, атака, конец хода, ход компьютера…) и его события.
 * Из ходов собирается журнал партии (в том числе после перезагрузки страницы).
 * События хранятся полностью (какую карту взял каждый); что скрыть от игрока — решает GameView при выдаче.
 */
#[ORM\Entity(repositoryClass: GameMoveRepository::class)]
#[ORM\Table(name: 'game_moves')]
#[ORM\Index(name: 'idx_game_moves_game', columns: ['game_id'])]
class GameMove
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<array<string, mixed>> $events GameView::normalize()
     */
    public function __construct(
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Game $game,

        // Чей был ход (индекс игрока); null — старт партии
        #[ORM\Column(type: Types::SMALLINT, nullable: true)]
        private ?int $actor,

        #[ORM\Column(type: Types::JSON)]
        private array $events,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getActor(): ?int
    {
        return $this->actor;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
