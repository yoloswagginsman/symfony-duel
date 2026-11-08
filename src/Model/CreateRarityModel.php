<?php

namespace App\Model;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateRarityModel
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите slug редкости.')]
        public string $slug,

        #[Assert\NotBlank(message: 'Укажите название редкости.')]
        public string $name,

        #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG, message: 'Цвет — в формате #RRGGBB.')]
        public string $colorHex,

        #[Assert\Range(notInRangeMessage: 'Шанс выпадения — от {{ min }} до {{ max }}.', min: 0, max: 100)]
        public int $dropChance,
    ) {
    }
}
