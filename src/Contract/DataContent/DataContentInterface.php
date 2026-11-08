<?php

namespace App\Contract\DataContent;

use App\Service\Content\Output\ContentImportResult;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Раздел контента (data/content): переносит свои данные из YAML в базу.
 * Какие разделы и в каком порядке импортировать, задаёт ContentImportCommand.
 */
#[AutoconfigureTag]
interface DataContentInterface
{
    /**
     * Имя раздела: ключ в отчёте импорта (для справочников — ещё и файл data/content/<name>.yaml).
     */
    public function getName(): string;

    /**
     * Создаёт новые и обновляет изменённые записи (без flush — им и транзакцией управляет ContentImporter).
     *
     * @param ContentImportResult $result итог раздела: сюда раздел пишет то, чего не видит Doctrine
     *                                    (ошибки, предупреждения); новые и изменённые записи импорт найдёт сам
     */
    public function import(ContentImportResult $result): void;
}
