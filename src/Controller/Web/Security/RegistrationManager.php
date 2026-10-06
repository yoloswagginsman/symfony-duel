<?php

namespace App\Controller\Web\Security;

use App\Controller\Web\Security\Output\RegistrationFormResult;
use App\Dto\RegisterUserFormDto;
use App\Form\RegistrationType;
use App\Service\UserService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

readonly class RegistrationManager
{
    public function __construct(
        private UserService $userService,
        private FormFactoryInterface $formFactory,
    ) {
    }

    public function getFormData(Request $request): RegistrationFormResult
    {
        $form = $this->formFactory->create(RegistrationType::class, new RegisterUserFormDto());
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new RegistrationFormResult(form: $form);
        }

        /** @var RegisterUserFormDto $registerFormDto */
        $registerFormDto = $form->getData();
        $user = $this->userService->register($registerFormDto->toModel());

        return new RegistrationFormResult(form: $form, user: $user);
    }
}
