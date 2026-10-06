<?php

namespace App\Service\Content;

/**
 * Запись контента не может быть превращена в карту: битая ссылка, нет обязательного поля и т.п.
 */
class ContentException extends \RuntimeException
{
}
