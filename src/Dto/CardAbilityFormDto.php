<?php

namespace App\Dto;

use App\Entity\Ability;

class CardAbilityFormDto
{
    public function __construct(
        public ?Ability $ability = null,
        public ?int $value = null,
        public bool $enabled = false,
    ) {
    }
}