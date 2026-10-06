<?php

namespace App\Dto\Api;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Тело POST /api/games и /api/games/{id}/join: какой колодой играть.
 */
final readonly class DeckChoiceDto
{
    public function __construct(
        #[Assert\NotNull(message: 'Укажите deckId — id колоды (GET /api/decks).')]
        #[Assert\Positive]
        public ?int $deckId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data тело запроса
     */
    public static function fromArray(array $data): self
    {
        return new self(is_int($data['deckId'] ?? null) ? $data['deckId'] : null);
    }
}
