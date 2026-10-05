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

    /** @var array<string, list<string>> запись => изменившиеся поля */
    public array $updated = [];

    /** @var list<string> записи в базе, которых нет в контенте (не удаляются) */
    public array $missing = [];

    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
