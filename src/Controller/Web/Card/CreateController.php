<?php

namespace App\Controller\Web\Card;

use App\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(UserRole::CREATOR->value)]
class CreateController extends AbstractController
{
    public function __construct(private readonly Manager $manager)
    {
    }

    #[Route(path: '/cards/new', name: 'app_cards_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $result = $this->manager->getFormData($request);

        if ($result->isSuccess()) {
            $this->addFlash('success', 'Карта успешно создана!');
            return $this->redirectToRoute('app_cards_show', ['id' => $result->card->getId()]);
        }

        return $this->render('card/new.html.twig', [
            'form' => $result->form->createView(),
            'card' => $result->card
        ]);
    }
}