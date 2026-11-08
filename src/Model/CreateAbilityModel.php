<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateAbilityModel
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите slug способности.')]
        public string $slug,

        #[Assert\NotBlank(message: 'Укажите название способности.')]
        public string $name,

        public ?string $description = null,
    ) {
    }
}
