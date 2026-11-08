<?php

namespace App\Contract\DataContent;

use App\Service\Content\Output\ContentImportResult;

/**
 * Раздел контента, который умеет удалять из базы записи, которых нет в YAML.
 * Вызывается только с --delete-missing, после import() и если в разделе нет ошибок.
 * Справочники этот контракт не реализуют: на их записи ссылаются карты.
 */
interface DeleteMissingInterface
{
    /**
     * Удаляет записи из ContentImportResult::$missing и переносит их в $deleted.
     */
    public function deleteMissing(ContentImportResult $result): void;
}
