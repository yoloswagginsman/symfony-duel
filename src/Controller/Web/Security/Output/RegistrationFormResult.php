<?php

namespace App\Controller\Web\Security\Output;

use App\Entity\User;
use Symfony\Component\Form\FormInterface;

readonly class RegistrationFormResult
{
    public function __construct(
        public FormInterface $form,
        public ?User $user = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->user !== null;
    }
}
