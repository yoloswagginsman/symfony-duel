<?php

namespace App\Controller\Web\Deck;

use App\Entity\Deck;
use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Кнопки списка колод: скопировать (базовую или свою) и удалить свою.
 */
#[IsGranted(UserRole::PLAYER->value)]
#[IsCsrfTokenValid('deck')]
class ActionController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/decks/{id}/copy', name: 'app_decks_copy', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function copy(Deck $deck, #[CurrentUser] User $user): Response
    {
        $copy = $this->manager->copy($deck, $user);
        $this->addFlash('success', sprintf('Создана колода «%s» — её можно менять.', $copy->getName()));

        return $this->redirectToRoute('app_decks_edit', ['id' => $copy->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route(path: '/decks/{id}/delete', name: 'app_decks_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(Deck $deck, #[CurrentUser] User $user): Response
    {
        $name = $deck->getName();
        $this->manager->delete($deck, $user);
        $this->addFlash('success', sprintf('Колода «%s» удалена.', $name));

        return $this->redirectToRoute('app_decks', status: Response::HTTP_SEE_OTHER);
    }
}
