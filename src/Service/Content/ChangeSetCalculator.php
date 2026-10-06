<?php

namespace App\Service\Content;

/**
 * Change set двух записей-массивов (например, CardYamlDto::toArray()): что изменится, если заменить $before на $after.
 * Формат — как у Doctrine UnitOfWork::getEntityChangeSet(): поле => [было, стало].
 * Ничего не знает о том, что это за записи.
 */
readonly class ChangeSetCalculator
{
    /**
     * Отсутствующее поле считается null; списки сравниваются без учёта порядка.
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     * @param list<string>         $ignore поля, которые не сравниваются (например, ключ записи)
     *
     * @return array<string, array{0: mixed, 1: mixed}> поле => [было, стало]
     */
    public function calculate(array $before, array $after, array $ignore = []): array
    {
        $changes = [];

        foreach (array_keys($before + $after) as $field) {
            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if (!in_array($field, $ignore, true) && $this->normalize($old) !== $this->normalize($new)) {
                $changes[$field] = [$old, $new];
            }
        }

        return $changes;
    }

    /**
     * Списки — отсортированы (вложенные тоже), чтобы порядок элементов не считался изменением.
     */
    private function normalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        $value = array_map($this->normalize(...), $value);
        if (array_is_list($value)) {
            sort($value);
        }

        return $value;
    }
}
