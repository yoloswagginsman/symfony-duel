<?php

namespace App\Game\Model;

use App\Game\GameRules;

final class PlayerState
{
    public int $fortification = GameRules::FORTIFICATION;
    public int $mana = 0;
    public int $maxMana = 0;

    /** @var list<CardInstance> */
    public array $hand = [];

    /** @var list<CardInstance> */
    public array $graveyard = [];

    /** @var array<int, ?CardInstance> ячейка => существо */
    public array $board;

    // Ландшафт на этой половине поля: свой — или враждебный, наложенный противником
    public ?CardInstance $landscape = null;

    /**
     * @param list<CardInstance> $deck верхняя карта — первая
     */
    public function __construct(
        public readonly int $index,
        public array $deck,
    ) {
        $this->board = array_fill(0, GameRules::BOARD_SIZE, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $cards = static fn (array $cards) => array_map(static fn (CardInstance $card) => $card->toArray(), $cards);

        return [
            'index' => $this->index,
            'fortification' => $this->fortification,
            'mana' => $this->mana,
            'maxMana' => $this->maxMana,
            'hand' => $cards($this->hand),
            'graveyard' => $cards($this->graveyard),
            'board' => array_map(static fn (?CardInstance $card) => $card?->toArray(), $this->board),
            'deck' => $cards($this->deck),
            'landscape' => $this->landscape?->toArray(),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $player = new self($data['index'], array_map(CardInstance::fromArray(...), $data['deck']));
        $player->fortification = $data['fortification'];
        $player->mana = $data['mana'];
        $player->maxMana = $data['maxMana'];
        $player->hand = array_map(CardInstance::fromArray(...), $data['hand']);
        $player->graveyard = array_map(CardInstance::fromArray(...), $data['graveyard']);
        $player->board = array_map(CardInstance::fromNullableArray(...), $data['board']);
        $player->landscape = CardInstance::fromNullableArray($data['landscape']);

        return $player;
    }
}
