<?php

namespace App\Dto\Api;

use App\Enum\GameActionType;
use App\Game\Action\ActionInterface;
use App\Game\Action\Attack;
use App\Game\Action\CapturePoint;
use App\Game\Action\EndTurn;
use App\Game\Action\PlayCard;
use App\Game\Action\Surrender;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Тело POST /api/games/{id}/actions. Игрок в нём не передаётся — он известен по токену.
 */
final readonly class GameActionDto
{
    public function __construct(
        #[Assert\NotNull(message: 'Укажите type: play_card, attack, capture_point, end_turn или surrender.')]
        public ?GameActionType $type = null,
        public ?int $cardId = null,
        public ?int $cell = null,
        public ?int $targetId = null,
        public ?int $attackerId = null,
        public ?int $creatureId = null,
        public ?int $point = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data тело запроса
     */
    public static function fromArray(array $data): self
    {
        $int = static fn (string $field) => is_int($data[$field] ?? null) ? $data[$field] : null;

        return new self(
            type: GameActionType::tryFrom((string) ($data['type'] ?? '')),
            cardId: $int('cardId'),
            cell: $int('cell'),
            targetId: $int('targetId'),
            attackerId: $int('attackerId'),
            creatureId: $int('creatureId'),
            point: $int('point'),
        );
    }

    #[Assert\Callback]
    public function validateFields(ExecutionContextInterface $context): void
    {
        $required = match ($this->type) {
            GameActionType::PLAY_CARD => ['cardId' => $this->cardId],
            GameActionType::ATTACK => ['attackerId' => $this->attackerId],
            GameActionType::CAPTURE_POINT => ['creatureId' => $this->creatureId, 'point' => $this->point],
            default => [],
        };

        foreach ($required as $field => $value) {
            if ($value === null) {
                $context->buildViolation(sprintf('Для %s укажите %s (число).', $this->type->value, $field))->atPath($field)->addViolation();
            }
        }
    }

    /**
     * Ход для движка. Вызывать после проверки (validateFields): нужные поля заполнены.
     */
    public function toAction(int $player): ActionInterface
    {
        return match ($this->type) {
            GameActionType::PLAY_CARD => new PlayCard($player, (int) $this->cardId, $this->cell, $this->targetId),
            GameActionType::ATTACK => new Attack($player, (int) $this->attackerId, $this->targetId),
            GameActionType::CAPTURE_POINT => new CapturePoint($player, (int) $this->creatureId, (int) $this->point),
            GameActionType::SURRENDER => new Surrender($player),
            GameActionType::END_TURN, null => new EndTurn($player),
        };
    }
}
