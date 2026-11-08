<?php

namespace App\EventListener;

use App\Game\Engine\IllegalActionException;
use App\Service\Game\GameException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Ошибки API (/api/*) — JSON {"error": "…"} с понятным статусом, а не HTML-страница.
 * Неизвестные ошибки (500) не трогаем — их показывает Symfony (в dev — с трассировкой).
 */
#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ApiExceptionListener
{
    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $exception = $event->getThrowable();
        $response = match (true) {
            $exception instanceof JsonException => $this->error('Тело запроса — JSON-объект.', Response::HTTP_BAD_REQUEST),
            $exception instanceof ValidationFailedException => $this->validationError($exception),
            // Ход не по правилам — партия не изменилась
            $exception instanceof IllegalActionException => $this->error($exception->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            $exception instanceof GameException => $this->error($exception->getMessage(), Response::HTTP_CONFLICT),
            // Другой запрос успел изменить партию раньше (оптимистичная блокировка)
            $exception instanceof OptimisticLockException => $this->error('Партия уже изменилась — обновите состояние и повторите ход.', Response::HTTP_CONFLICT),
            $exception instanceof HttpExceptionInterface => $this->error($exception->getMessage(), $exception->getStatusCode()),
            default => null,
        };

        if ($response !== null) {
            $event->setResponse($response);
        }
    }

    private function error(string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status);
    }

    private function validationError(ValidationFailedException $exception): JsonResponse
    {
        $violations = [];
        foreach ($exception->getViolations() as $violation) {
            $violations[$violation->getPropertyPath()] = $violation->getMessage();
        }

        return new JsonResponse(['error' => 'Неверные данные запроса.', 'violations' => $violations], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
