<?php

namespace App\Controller\Web\Play;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\UserRole;
use App\Service\Game\GameException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Кнопки лобби: играть с компьютером, создать партию, присоединиться. Не вышло — сообщение в лобби.
 */
#[IsGranted(UserRole::PLAYER->value)]
#[IsCsrfTokenValid('play')]
class StartController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/play/computer', name: 'app_play_computer', methods: ['POST'])]
    public function computer(#[CurrentUser] User $user, Request $request): Response
    {
        return $this->attempt(fn () => $this->manager->playComputer($user, $request));
    }

    #[Route(path: '/play/create', name: 'app_play_create', methods: ['POST'])]
    public function create(#[CurrentUser] User $user, Request $request): Response
    {
        return $this->attempt(fn () => $this->manager->create($user, $request));
    }

    #[Route(path: '/play/{id}/join', name: 'app_play_join', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function join(Game $game, #[CurrentUser] User $user, Request $request): Response
    {
        return $this->attempt(function () use ($game, $user, $request) {
            $this->manager->join($game, $user, $request);

            return $game;
        });
    }

    /**
     * @param callable(): Game $start
     */
    private function attempt(callable $start): Response
    {
        try {
            $game = $start();
        } catch (GameException|AccessDeniedException|NotFoundHttpException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('app_play', status: Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_play_game', ['id' => $game->getId()], Response::HTTP_SEE_OTHER);
    }
}
