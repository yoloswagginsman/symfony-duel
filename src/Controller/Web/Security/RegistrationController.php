<?php

namespace App\Controller\Web\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    public function __construct(private readonly RegistrationManager $manager)
    {
    }

    #[Route(path: '/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, Security $security): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $result = $this->manager->getFormData($request);

        if ($result->isSuccess()) {
            $this->addFlash('success', sprintf('Добро пожаловать в дуэль, %s!', $result->user->getNickname()));
            $security->login($result->user, 'form_login', 'main');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'form' => $result->form,
        ]);
    }
}
