<?php

namespace App\Controller\Web\Card\Output;

use App\Entity\Card;
use Symfony\Component\Form\FormInterface;

readonly class CardFormResult
{
    public function __construct(
        public FormInterface $form,
        public bool $isNew,
        public ?Card $card = null,
        public bool $isSubmittedAndValid = false,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->isSubmittedAndValid;
    }

    public function isEdit(): bool
    {
        return !$this->isNew;
    }
}