<?php

namespace App\Service\Content;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Файлы контента (data/content): справочники и cards.yaml.
 */
readonly class ContentStorage
{
    /**
     * Формат записи cards.yaml — часть кода, а не настройка: от него зависит вставка пустых строк ниже.
     * Компактный «- name: …» и [] для пустых списков; с уровня 3 (теги, способности) — в одну строку.
     */
    private const int DUMP_FLAGS = Yaml::DUMP_COMPACT_NESTED_MAPPING | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE;
    private const int DUMP_INLINE_LEVEL = 3;
    private const int DUMP_INDENT = 2;

    /**
     * Каталог и имя файла — в config/services.yaml (app.content.*).
     */
    public function __construct(
        private string $directory,
        private string $cardsFile,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadCards(): array
    {
        return Yaml::parseFile($this->cardsPath())['cards'] ?? [];
    }

    /**
     * Справочник: races, card_types, rarities, tags, abilities (файл <name>.yaml, корневой ключ <name>).
     *
     * @return list<array<string, mixed>>
     */
    public function loadReference(string $name): array
    {
        return Yaml::parseFile($this->directory . '/' . $name . '.yaml')[$name] ?? [];
    }

    /**
     * @param list<array<string, mixed>> $cards
     */
    public function saveCards(array $cards): void
    {
        $yaml = Yaml::dump(['cards' => array_values($cards)], self::DUMP_INLINE_LEVEL, self::DUMP_INDENT, self::DUMP_FLAGS);

        // Пустая строка между картами (но не перед первой) — для читаемости при ручной правке
        $yaml = preg_replace('/(?<!cards:)\n(?=  - )/', "\n\n", $yaml);

        $this->filesystem->dumpFile($this->cardsPath(), $yaml);
    }

    private function cardsPath(): string
    {
        return $this->directory . '/' . $this->cardsFile;
    }
}
