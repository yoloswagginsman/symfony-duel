<?php

namespace App\DataFixtures;

use App\Enum\UserRole;
use App\Model\RegisterUserModel;
use App\Service\UserService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Тестовые аккаунты по одному на роль. Только для разработки!
 * Загрузить, не трогая карты: php bin/console doctrine:fixtures:load --group=users --append
 */
class UserFixtures extends Fixture implements FixtureGroupInterface
{
    private const USERS = [
        ['admin@duel.ru', 'Архимаг', UserRole::ADMIN],
        ['creator@duel.ru', 'Кузнец', UserRole::CREATOR],
        ['player@duel.ru', 'Странник', UserRole::PLAYER],
    ];

    private const PASSWORD = 'password';

    public function __construct(private readonly UserService $userService)
    {
    }

    public static function getGroups(): array
    {
        return ['users'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::USERS as [$email, $nickname, $role]) {
            $this->userService->register(new RegisterUserModel(
                nickname: $nickname,
                email: $email,
                plainPassword: self::PASSWORD,
                role: $role,
            ));
        }
    }
}
