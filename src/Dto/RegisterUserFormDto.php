<?php

namespace App\Dto;

use App\Contract\Dto\ToModelConvertibleInterface;
use App\Entity\User;
use App\Model\RegisterUserModel;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @implements ToModelConvertibleInterface<RegisterUserModel>
 */
#[UniqueEntity(fields: ['email'], message: 'Этот email уже занят.', entityClass: User::class)]
#[UniqueEntity(fields: ['nickname'], message: 'Этот никнейм уже занят.', entityClass: User::class)]
class RegisterUserFormDto implements ToModelConvertibleInterface
{
    public function __construct(
        #[Assert\NotBlank(message: 'Укажите никнейм.')]
        #[Assert\Length(min: 3, max: 50, minMessage: 'Никнейм должен быть не короче {{ limit }} символов.')]
        #[Assert\Regex(pattern: '/^[\p{L}\d_\-]+$/u', message: 'Никнейм может содержать только буквы, цифры, «_» и «-».')]
        public ?string $nickname = null,

        #[Assert\NotBlank(message: 'Укажите email.')]
        #[Assert\Email(message: 'Некорректный email.')]
        #[Assert\Length(max: 180)]
        public ?string $email = null,

        #[Assert\NotBlank(message: 'Придумайте пароль.')]
        #[Assert\Length(min: 6, max: 4096, minMessage: 'Пароль должен быть не короче {{ limit }} символов.')]
        public ?string $plainPassword = null,
    ) {
    }

    public function toModel(): RegisterUserModel
    {
        return new RegisterUserModel(
            nickname: trim((string) $this->nickname),
            email: mb_strtolower(trim((string) $this->email)),
            plainPassword: (string) $this->plainPassword,
        );
    }
}
