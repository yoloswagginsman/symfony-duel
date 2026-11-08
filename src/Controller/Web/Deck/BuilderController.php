<?php

namespace App\Controller\Web\Deck;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Конструктор колоды: новая и правка своей. Набор карт — Stimulus (deck_builder), сохранение — обычная форма.
 */
#[IsGranted(UserRole::PLAYER->value)]
class BuilderController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/decks/new', name: 'app_decks_new', methods: ['GET', 'POST'])]
    #[IsCsrfTokenValid('deck', methods: ['POST'])]
    public function new(Request $request, #[CurrentUser] User $user): Response
    {
        return $this->handle($request, $user);
    }

    #[Route(path: '/decks/{id}/edit', name: 'app_decks_edit', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsCsrfTokenValid('deck', methods: ['POST'])]
    public function edit(Deck $deck, Request $request, #[CurrentUser] User $user): Response
    {
        return $this->handle($request, $user, $this->manager->ownDeck($deck, $user));
    }

    private function handle(Request $request, User $user, ?Deck $deck = null): Response
    {
        $result = $this->manager->form($request, $user, $deck);

        if ($result->saved) {
            $this->addFlash('success', sprintf('Колода «%s» сохранена.', $result->name));

            return $this->redirectToRoute('app_decks', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('deck/builder.html.twig', ['form' => $result], new Response(status: $result->errors !== [] ? 422 : 200));
    }
}
