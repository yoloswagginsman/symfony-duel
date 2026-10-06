<?php

namespace App\Controller\Web\Deck;

use App\Controller\Web\Deck\Output\DeckFormResult;
use App\Entity\Card;
use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\User;
use App\Model\CreateDeckModel;
use App\Repository\CardRepository;
use App\Repository\DeckRepository;
use App\Service\DeckService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Колоды игрока на сайте: список, конструктор, копия, удаление.
 * Правила колоды проверяет CreateDeckModel (через DeckService) — те же, что у базовых колод.
 */
readonly class Manager
{
    public function __construct(
        private DeckService $deckService,
        private DeckRepository $deckRepository,
        private CardRepository $cardRepository,
    ) {
    }

    /**
     * @return array{base: list<Deck>, mine: list<Deck>}
     */
    public function list(User $user): array
    {
        return [
            'base' => $this->deckRepository->findBy(['owner' => null], ['name' => 'ASC']),
            'mine' => $this->deckRepository->findBy(['owner' => $user], ['name' => 'ASC']),
        ];
    }

    /**
     * Своя колода игрока; чужая и базовая (её меняют через контент) — как будто её нет.
     */
    public function ownDeck(Deck $deck, User $user): Deck
    {
        if ($deck->getOwner() !== $user) {
            throw new NotFoundHttpException('Колода не найдена.');
        }

        return $deck;
    }

    /**
     * Конструктор: показать форму или сохранить отправленную. $deck = null — новая колода.
     */
    public function form(Request $request, User $user, ?Deck $deck = null): DeckFormResult
    {
        $pool = $this->cardRepository->findDeckPool();

        if (!$request->isMethod('POST')) {
            return new DeckFormResult(
                pool: $pool,
                name: $deck?->getName() ?? '',
                description: $deck?->getDescription(),
                quantities: $deck !== null ? $this->quantitiesOf($deck) : [],
                deck: $deck,
            );
        }

        $payload = $request->getPayload();
        $name = trim($payload->getString('name'));
        $description = trim($payload->getString('description')) ?: null;
        $quantities = $this->postedQuantities($payload->all('cards'));
        $model = new CreateDeckModel(
            name: $name,
            cards: $this->cardsFor($quantities, $pool),
            owner: $user,
            description: $description,
        );

        try {
            $deck = $deck === null ? $this->deckService->create($model) : $this->deckService->update($deck, $model);
        } catch (ValidationFailedException $exception) {
            $errors = [];
            foreach ($exception->getViolations() as $violation) {
                $errors[] = (string) $violation->getMessage();
            }

            return new DeckFormResult($pool, $name, $description, $quantities, $errors, $deck);
        }

        return new DeckFormResult($pool, $name, $description, $quantities, deck: $deck, saved: true);
    }

    /**
     * Копия колоды (базовой или своей) — в свои колоды, её можно менять.
     */
    public function copy(Deck $deck, User $user): Deck
    {
        if (!$deck->isBase()) {
            $this->ownDeck($deck, $user);
        }

        return $this->deckService->create(new CreateDeckModel(
            name: sprintf('%s (копия)', $deck->getName()),
            cards: array_map(
                static fn (DeckCard $deckCard) => ['card' => $deckCard->getCard(), 'quantity' => $deckCard->getQuantity()],
                $deck->getCards()->getValues(),
            ),
            owner: $user,
            description: $deck->getDescription(),
        ));
    }

    public function delete(Deck $deck, User $user): void
    {
        $this->deckService->delete($this->ownDeck($deck, $user));
    }

    /**
     * @return array<int, int> id карты => копий
     */
    private function quantitiesOf(Deck $deck): array
    {
        $quantities = [];
        foreach ($deck->getCards() as $deckCard) {
            $quantities[(int) $deckCard->getCard()->getId()] = $deckCard->getQuantity();
        }

        return $quantities;
    }

    /**
     * cards[<id карты>] = <копий> из формы; нули и мусор отбрасываются.
     *
     * @param array<mixed> $posted
     *
     * @return array<int, int>
     */
    private function postedQuantities(array $posted): array
    {
        $quantities = [];
        foreach ($posted as $cardId => $quantity) {
            if (is_numeric($cardId) && is_numeric($quantity) && (int) $quantity > 0) {
                $quantities[(int) $cardId] = (int) $quantity;
            }
        }

        return $quantities;
    }

    /**
     * Только карты из пула: постройку или несуществующую карту в колоду не положить.
     *
     * @param array<int, int> $quantities
     * @param list<Card>      $pool
     *
     * @return list<array{card: Card, quantity: int}>
     */
    private function cardsFor(array $quantities, array $pool): array
    {
        $cards = [];
        foreach ($pool as $card) {
            if (isset($quantities[$card->getId()])) {
                $cards[] = ['card' => $card, 'quantity' => $quantities[$card->getId()]];
            }
        }

        return $cards;
    }
}
