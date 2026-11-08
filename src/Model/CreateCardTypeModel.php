<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateCardTypeModel
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите slug типа карты.')]
        public string $slug,

        #[Assert\NotBlank(message: 'Укажите название типа карты.')]
        public string $name,
    ) {
    }
}
