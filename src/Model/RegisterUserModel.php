<?php

namespace App\Model;

use App\Enum\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

readonly class RegisterUserModel
{
    public function __construct(
        #[Assert\NotBlank(message: 'Никнейм обязателен.')]
        #[Assert\Length(min: 3, max: 50)]
        public string $nickname,

        #[Assert\NotBlank(message: 'Email обязателен.')]
        #[Assert\Email]
        public string $email,

        #[Assert\NotBlank(message: 'Пароль обязателен.')]
        #[Assert\Length(min: 6, max: 4096)]
        public string $plainPassword,

        public UserRole $role = UserRole::PLAYER,
    ) {
    }
}
