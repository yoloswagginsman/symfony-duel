<?php

namespace App\Controller\Web\Deck;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(UserRole::PLAYER->value)]
class IndexController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/decks', name: 'app_decks', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->render('deck/index.html.twig', $this->manager->list($user));
    }
}
