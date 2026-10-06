<?php

namespace App\Service\Content;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Файлы контента (data/content): справочники, карты и decks.yaml.
 *
 * Карт много, поэтому они разложены по файлам: cards/<тип>/<раса>.yaml
 * (cards/creature/elves.yaml, cards/spell/undead.yaml, cards/building/neutral.yaml…).
 * Читаются все файлы папки; при записи (карты из интерфейса) раскладка восстанавливается по этому правилу —
 * карта, у которой сменили расу или тип, переезжает в свой файл.
 */
readonly class ContentStorage
{
    /**
     * Формат записи файлов карт — часть кода, а не настройка: от него зависит вставка пустых строк ниже.
     * Компактный «- name: …» и [] для пустых списков; с уровня 3 (теги, способности) — в одну строку.
     */
    private const int DUMP_FLAGS = Yaml::DUMP_COMPACT_NESTED_MAPPING | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE;
    private const int DUMP_INLINE_LEVEL = 3;
    private const int DUMP_INDENT = 2;

    // Карта без типа или расы (ошибка в контенте) — всё равно сохраняется, чтобы её можно было исправить
    private const string UNKNOWN = 'unknown';

    /**
     * Каталог контента и папка карт — в config/services.yaml (app.content.*).
     */
    public function __construct(
        private string $directory,
        private string $cardsDirectory,
        private Filesystem $filesystem,
    ) {
    }

    /**
     * Все карты подряд — файлы по алфавиту, внутри файла — как записаны.
     *
     * @return list<array<string, mixed>>
     */
    public function loadCards(): array
    {
        return array_merge([], ...array_values($this->loadCardFiles()));
    }

    /**
     * Карты по файлам: путь от папки карт (creature/elves.yaml) => записи.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function loadCardFiles(): array
    {
        if (!is_dir($this->cardsPath())) {
            return [];
        }

        $files = [];
        foreach ((new Finder())->files()->in($this->cardsPath())->name('*.yaml')->sortByName() as $file) {
            $files[$file->getRelativePathname()] = Yaml::parseFile($file->getPathname())['cards'] ?? [];
        }

        return $files;
    }

    /**
     * Записать все карты, разложив по файлам cards/<тип>/<раса>.yaml. Файлы, в которых карт не осталось, удаляются.
     *
     * @param list<array<string, mixed>> $cards
     */
    public function saveCards(array $cards): void
    {
        $files = [];
        foreach ($cards as $card) {
            $files[$this->cardFile($card)][] = $card;
        }

        foreach (array_keys($this->loadCardFiles()) as $existing) {
            if (!isset($files[$existing])) {
                $this->filesystem->remove(Path::join($this->cardsPath(), $existing));
            }
        }

        foreach ($files as $file => $fileCards) {
            $this->filesystem->dumpFile(Path::join($this->cardsPath(), $file), $this->dumpCards($fileCards));
        }
    }

    /**
     * Файл карты по правилу раскладки: <тип>/<раса>.yaml.
     *
     * @param array<string, mixed> $card
     */
    public function cardFile(array $card): string
    {
        return sprintf('%s/%s.yaml', $card['type'] ?? self::UNKNOWN, $card['race'] ?? self::UNKNOWN);
    }

    /**
     * Базовые колоды: data/content/decks.yaml, корневой ключ decks.
     *
     * @return list<array<string, mixed>>
     */
    public function loadDecks(): array
    {
        return Yaml::parseFile($this->directory . '/decks.yaml')['decks'] ?? [];
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
    private function dumpCards(array $cards): string
    {
        $yaml = Yaml::dump(['cards' => array_values($cards)], self::DUMP_INLINE_LEVEL, self::DUMP_INDENT, self::DUMP_FLAGS);

        // Пустая строка между картами (но не перед первой) — для читаемости при ручной правке
        return preg_replace('/(?<!cards:)\n(?=  - )/', "\n\n", $yaml);
    }

    private function cardsPath(): string
    {
        return Path::join($this->directory, $this->cardsDirectory);
    }
}
