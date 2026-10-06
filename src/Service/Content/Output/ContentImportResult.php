<?php

namespace App\Service\Content\Output;

/**
 * Итог импорта одного раздела контента.
 */
class ContentImportResult
{
    public function __construct(
        // Имя раздела (races, cards, …)
        public readonly string $name,
    ) {
    }

    /** @var list<string> */
    public array $created = [];

    /** @var array<string, array<string, array{0: mixed, 1: mixed}>> запись => [поле => [было, стало]] */
    public array $updated = [];

    /** @var list<string> записи в базе, которых нет в контенте (остаются в базе) */
    public array $missing = [];

    /** @var list<string> записи, которых нет в контенте и которые удалены (--delete-missing) */
    public array $deleted = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
