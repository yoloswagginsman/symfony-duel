<?php

namespace App\Command;

use App\DataContent\AbilityYaml;
use App\DataContent\CardYaml;
use App\DataContent\CardTypeYaml;
use App\DataContent\RaceYaml;
use App\DataContent\RarityYaml;
use App\DataContent\TagYaml;
use App\Service\Content\ContentImporter;
use App\Service\Content\Output\ContentImportResult;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:content:import',
    description: 'Загружает контент (data/content) в базу: справочники и карты',
    help: <<<'HELP'
        Импортирует разделы контента по одному: сначала справочники, затем карты.
        Справочники сопоставляются по slug, карты — по vendorCode.
        Новые записи создаются, изменённые обновляются; записи, которых нет в контенте, не удаляются.
        Каждый раздел — в своей транзакции: при ошибке в разделе он не меняется, следующие не запускаются.

          <info>php %command.full_name% --dry-run</info>          показать, что изменится
          <info>php %command.full_name%</info>                    применить
          <info>php %command.full_name% --delete-missing</info>   также удалить карты, которых нет в контенте (с картинками)
        HELP,
)]
final readonly class ContentImportCommand
{
    public function __construct(
        private ContentImporter $importer,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Только показать изменения, ничего не записывать')]
        bool $dryRun = false,
        #[Option(description: 'Удалить из базы карты, которых нет в контенте (вместе с картинками)')]
        bool $deleteMissing = false,
    ): int {
        $io->title('Импорт контента' . ($dryRun ? ' (dry-run)' : ''));

        $dataContents = [
            // Справочники — на них ссылаются карты
            RaceYaml::class,
            CardTypeYaml::class,
            RarityYaml::class,
            TagYaml::class,
            AbilityYaml::class,
            // Карты — последними
            CardYaml::class,
        ];

        $results = [];
        foreach ($dataContents as $class) {
            $results[] = $result = $this->import($io, $class, $dryRun, $deleteMissing);

            // Ошибки в разделе — следующим разделам не на что опираться
            if ($result->hasErrors()) {
                $this->summary($io, $results, $dryRun);

                return Command::FAILURE;
            }
        }

        $this->summary($io, $results, $dryRun);

        if ($dryRun) {
            $io->note('Dry-run: изменения не сохранены. Запустите без --dry-run, чтобы применить.');
        } else {
            $io->success('Контент загружен.');
        }

        return Command::SUCCESS;
    }

    /**
     * Импортирует раздел и печатает его отчёт. false — в разделе ошибки.
     * @throws Throwable
     */
    /**
     * Импортирует раздел и печатает его отчёт.
     */
    private function import(SymfonyStyle $io, string $class, bool $dryRun, bool $deleteMissing): ContentImportResult
    {
        $result = $this->importer->import($class, $dryRun, $deleteMissing);

        $io->section($result->name);

        $rows = [];
        foreach ($result->created as $record) {
            $rows[] = ['<info>+ создать</info>', $record, ''];
        }
        foreach ($result->updated as $record => $changes) {
            $lines = [];
            foreach ($changes as $field => [$before, $after]) {
                $lines[] = sprintf('%s: %s → %s', $field, $this->format($before), $this->format($after));
            }
            $rows[] = ['<comment>~ обновить</comment>', $record, implode("\n", $lines)];
        }
        foreach ($result->deleted as $record) {
            $rows[] = ['<fg=red>- удалить</>', $record, 'нет в контенте'];
        }
        foreach ($result->missing as $record) {
            $rows[] = ['<fg=gray>? нет в контенте</>', $record, 'остаётся в базе (удалит --delete-missing)'];
        }
        $rows === [] ? $io->text('Без изменений') : $io->table(['Действие', 'Запись', 'Изменения'], $rows);

        if ($result->warnings !== []) {
            $io->warning($result->warnings);
        }
        if ($result->hasErrors()) {
            $io->error(['Ошибки в разделе, он не изменён; следующие разделы не запускались:', ...$result->errors]);
        }

        return $result;
    }

    /**
     * Сводка: сколько записей создано и обновлено по каждому разделу и всего.
     *
     * @param list<ContentImportResult> $results
     */
    private function summary(SymfonyStyle $io, array $results, bool $dryRun): void
    {
        $rows = [];
        $total = [0, 0, 0, 0];
        foreach ($results as $result) {
            $counts = [count($result->created), count($result->updated), count($result->deleted), count($result->missing)];
            $rows[] = [$result->name, ...$counts];
            $total = array_map(static fn (int $sum, int $count) => $sum + $count, $total, $counts);
        }
        $rows[] = new TableSeparator();
        $rows[] = ['<options=bold>Итого</>', ...$total];

        $io->section('Сводка');
        $io->table(
            [
                'Раздел',
                $dryRun ? 'Создать' : 'Создано',
                $dryRun ? 'Обновить' : 'Обновлено',
                $dryRun ? 'Удалить' : 'Удалено',
                'Нет в контенте (остаются)',
            ],
            $rows,
        );
    }

    /**
     * Значение поля для отчёта: списки — через запятую, запись вида {name: Heal, value: 2} — «Heal=2», пусто — «—».
     */
    private function format(mixed $value): string
    {
        return match (true) {
            $value === null, $value === [], $value === '' => '—',
            is_bool($value) => $value ? 'да' : 'нет',
            $value instanceof \BackedEnum => (string) $value->value,
            is_array($value) && array_is_list($value) => implode(', ', array_map($this->format(...), $value)),
            is_array($value) => implode('=', $value),
            default => mb_strimwidth((string) $value, 0, 60, '…'),
        };
    }
}
