<?php

namespace App\Controller\Web\Deck\Output;

use App\Entity\Card;
use App\Entity\Deck;

/**
 * Конструктор колоды: что показать в форме и сохранилась ли колода.
 */
readonly class DeckFormResult
{
    /**
     * @param list<Card>       $pool       карты, которые можно положить в колоду
     * @param array<int, int>  $quantities id карты => копий (выбранное в форме)
     * @param list<string>     $errors     почему колода не сохранилась
     */
    public function __construct(
        public array $pool,
        public string $name,
        public ?string $description,
        public array $quantities,
        public array $errors = [],
        public ?Deck $deck = null,
        public bool $saved = false,
    ) {
    }
}
