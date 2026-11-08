<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateRaceModel
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите slug расы.')]
        public string $slug,

        #[Assert\NotBlank(message: 'Укажите название расы.')]
        public string $name,

        public ?string $description = null,
    ) {
    }
}
