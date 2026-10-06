<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Что лежит в JWT. Токен подписан, но не зашифрован — его читает любой, у кого он есть,
 * поэтому email в нём нет. Игрок — по id (sub, по нему же API находит пользователя),
 * nickname — чтобы клиенту не делать лишний запрос. Партий и колод в токене нет:
 * они меняются, а права на партию проверяет сервер. iat/exp добавит сам lexik.
 */
#[AsEventListener(event: Events::JWT_CREATED)]
final class JwtPayloadListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $event->setData([
            'sub' => (string) $user->getId(),
            'nickname' => $user->getNickname(),
            'roles' => $user->getRoles(),
        ]);
    }
}
