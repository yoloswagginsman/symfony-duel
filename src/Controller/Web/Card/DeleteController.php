<?php

namespace App\Controller\Web\Card;

use App\Entity\Card;
use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(UserRole::CREATOR->value)]
class DeleteController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/cards/{id}', name: 'app_cards_delete', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    public function delete(Request $request, Card $card): Response
    {
        $this->manager->delete($request, $card);
        return $this->redirectToRoute('app_cards_index', [], Response::HTTP_SEE_OTHER);
    }
}
