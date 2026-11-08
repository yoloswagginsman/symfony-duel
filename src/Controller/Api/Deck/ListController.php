<?php

namespace App\Controller\Api\Deck;

use App\Entity\Deck;
use App\Entity\DeckCard;
use App\Entity\User;
use App\Repository\DeckRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Колоды, которыми можно играть: базовые и свои.
 */
class ListController extends AbstractController
{
    public function __construct(private readonly DeckRepository $deckRepository)
    {
    }

    #[Route(path: '/api/decks', name: 'api_decks_list', methods: ['GET'])]
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(array_map(
            static fn (Deck $deck) => [
                'id' => $deck->getId(),
                'name' => $deck->getName(),
                'description' => $deck->getDescription(),
                'base' => $deck->isBase(),
                'cards' => array_map(
                    static fn (DeckCard $deckCard) => [
                        'vendorCode' => $deckCard->getCard()->getVendorCode(),
                        'name' => $deckCard->getCard()->getName(),
                        'count' => $deckCard->getQuantity(),
                    ],
                    $deck->getCards()->getValues(),
                ),
            ],
            $this->deckRepository->findAvailableFor($user),
        ));
    }
}
