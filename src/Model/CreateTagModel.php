<?php

namespace App\Model;

use App\Enum\TagCategory;
use Symfony\Component\Validator\Constraints as Assert;

readonly class CreateTagModel
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите slug тега.')]
        public string $slug,

        #[Assert\NotBlank(message: 'Укажите название тега.')]
        public string $name,

        public TagCategory $category,

        #[Assert\CssColor(formats: Assert\CssColor::HEX_LONG, message: 'Цвет — в формате #RRGGBB.')]
        public string $colorHex,

        public ?string $icon = null,
    ) {
    }
}
