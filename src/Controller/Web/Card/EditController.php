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
class EditController extends AbstractController
{
    #[Route(path: '/cards/{id}/edit', name: 'app_cards_edit', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Card $card,
        Manager $manager
    ): Response {
        $result = $manager->getFormData($request, $card);

        if ($result->isSuccess()) {
            $this->addFlash('success', 'Карта успешно обновлена!');
            return $this->redirectToRoute('app_cards_show', ['id' => $result->card->getId()]);
        }

        return $this->render('card/form.html.twig', [
            'form' => $result->form,
            'card' => $result->card,
            'isEdit' => $result->isEdit(),
        ]);
    }
}