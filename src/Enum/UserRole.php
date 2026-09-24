<?php

namespace App\Enum;

/**
 * Роли пользователей. Иерархия задана в config/packages/security.yaml:
 * Администратор ⊃ Создатель ⊃ Игрок.
 */
enum UserRole: string
{
    case ADMIN = 'ROLE_ADMIN';
    case CREATOR = 'ROLE_CREATOR';
    case PLAYER = 'ROLE_PLAYER';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Администратор',
            self::CREATOR => 'Создатель',
            self::PLAYER => 'Игрок',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ADMIN => '👑',
            self::CREATOR => '⚒️',
            self::PLAYER => '🛡️',
        };
    }
}
