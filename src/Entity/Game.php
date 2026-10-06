<?php

namespace App\Entity;

use App\Enum\GameStatus;
use App\Repository\GameRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Партия. Игроки — по индексу движка: player0 ходит первым (порядок случайный, решается при старте).
 * В партии с компьютером его место — null, а его индекс — в computerPlayer.
 * Состояние — GameState::toArray() в JSON; пока партия ждёт второго игрока, состояния нет.
 */
#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table(name: 'games')]
#[ORM\Index(name: 'idx_games_status', columns: ['status'])]
class Game
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: GameStatus::class)]
    private GameStatus $status = GameStatus::WAITING;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $player0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $player1 = null;

    // Колода создателя — нужна, пока партия ждёт второго игрока
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Deck $creatorDeck = null;

    // Зерно партии: порядок ходов, колоды, постройки на точках — партию можно воспроизвести
    #[ORM\Column(nullable: true)]
    private ?int $seed = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $state = null;

    // Индекс игрока-компьютера (0 или 1); null — играют два человека
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $computerPlayer = null;

    // Победитель — индекс (есть и у компьютера) и пользователь (для статистики; у компьютера — null)
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $winnerIndex = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $winner = null;

    // До какого момента активный игрок должен сходить; истёк — ход завершается за него (GameService::expireTurn)
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $turnDeadline = null;

    // Оптимистичная блокировка: два хода одновременно — второй получит ошибку, а не затрёт первый
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $creator, ?Deck $creatorDeck)
    {
        $this->player0 = $creator;
        $this->creatorDeck = $creatorDeck;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function getPlayer0(): ?User
    {
        return $this->player0;
    }

    public function getPlayer1(): ?User
    {
        return $this->player1;
    }

    public function getCreatorDeck(): ?Deck
    {
        return $this->creatorDeck;
    }

    /**
     * Пользователь по индексу движка; null — место компьютера или ещё свободно.
     */
    public function playerAt(int $index): ?User
    {
        return $index === 0 ? $this->player0 : $this->player1;
    }

    /**
     * Индекс игрока в движке или null — пользователь не участвует в партии.
     */
    public function playerIndexOf(User $user): ?int
    {
        return match ($user) {
            $this->player0 => 0,
            $this->player1 => 1,
            default => null,
        };
    }

    public function getComputerPlayer(): ?int
    {
        return $this->computerPlayer;
    }

    public function isComputerTurn(int $activePlayer): bool
    {
        return $this->computerPlayer === $activePlayer;
    }

    public function getSeed(): ?int
    {
        return $this->seed;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getState(): ?array
    {
        return $this->state;
    }

    public function getWinner(): ?User
    {
        return $this->winner;
    }

    /**
     * Партия начинается: порядок ходов уже решён — $first ходит первым.
     * null — место компьютера ($computerPlayer — его индекс).
     *
     * @param array<string, mixed> $state
     */
    public function start(?User $first, ?User $second, int $seed, array $state, ?int $computerPlayer = null): void
    {
        $this->player0 = $first;
        $this->player1 = $second;
        $this->seed = $seed;
        $this->computerPlayer = $computerPlayer;
        $this->creatorDeck = null;
        $this->status = GameStatus::ACTIVE;
        $this->saveState($state);
    }

    /**
     * @param array<string, mixed> $state
     */
    public function saveState(array $state): void
    {
        $this->state = $state;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function finish(int $winnerIndex): void
    {
        $this->winnerIndex = $winnerIndex;
        $this->winner = $this->playerAt($winnerIndex);
        $this->status = GameStatus::FINISHED;
        $this->turnDeadline = null;
    }

    public function getTurnDeadline(): ?\DateTimeImmutable
    {
        return $this->turnDeadline;
    }

    public function setTurnDeadline(?\DateTimeImmutable $turnDeadline): void
    {
        $this->turnDeadline = $turnDeadline;
    }

    public function getWinnerIndex(): ?int
    {
        return $this->winnerIndex;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
