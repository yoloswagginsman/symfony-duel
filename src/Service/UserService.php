<?php

namespace App\Service;

use App\Entity\User;
use App\Model\RegisterUserModel;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private EntityManagerInterface $em,
    ) {
    }

    public function register(RegisterUserModel $model): User
    {
        $this->validateModel($model);

        return $this->em->wrapInTransaction(function () use ($model) {
            $user = (new User())
                ->setNickname($model->nickname)
                ->setEmail($model->email)
                ->addRole($model->role);

            // В базу попадает только хеш, открытый пароль дальше модели не уходит
            $user->setPassword($this->passwordHasher->hashPassword($user, $model->plainPassword));

            $this->userRepository->store($user);

            return $user;
        });
    }

    private function validateModel(RegisterUserModel $model): void
    {
        $violations = $this->validator->validate($model);
        if ($violations->count() > 0) {
            throw new ValidationFailedException($model, $violations);
        }
    }
}
