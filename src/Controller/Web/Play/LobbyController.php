<?php

namespace App\Controller\Web\Play;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(UserRole::PLAYER->value)]
class LobbyController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/play', name: 'app_play', methods: ['GET'])]
    public function lobby(#[CurrentUser] User $user): Response
    {
        return $this->render('play/lobby.html.twig', ['lobby' => $this->manager->lobby($user)]);
    }
}
