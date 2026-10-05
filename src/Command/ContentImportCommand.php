<?php

namespace App\Command;

use App\DataContent\AbilityYaml;
use App\DataContent\CardYaml;
use App\DataContent\CardTypeYaml;
use App\DataContent\RaceYaml;
use App\DataContent\RarityYaml;
use App\DataContent\TagYaml;
use App\Service\Content\ContentImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:content:import',
    description: 'Загружает контент (data/content) в базу: справочники и карты',
    help: <<<'HELP'
        Импортирует разделы контента по одному: сначала справочники, затем карты.
        Справочники сопоставляются по slug, карты — по vendorCode.
        Новые записи создаются, изменённые обновляются; записи, которых нет в контенте, не удаляются.
        Каждый раздел — в своей транзакции: при ошибке в разделе он не меняется, следующие не запускаются.

          <info>php %command.full_name% --dry-run</info>   показать, что изменится
          <info>php %command.full_name%</info>             применить
        HELP,
)]
final readonly class ContentImportCommand
{
    public function __construct(
        private ContentImporter $importer,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Только показать изменения, ничего не записывать')]
        bool $dryRun = false,
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

        foreach ($dataContents as $class) {
            // Ошибки в разделе — следующим разделам не на что опираться
            if (!$this->import($io, $class, $dryRun)) {
                return Command::FAILURE;
            }
        }

        if ($dryRun) {
            $io->note('Dry-run: изменения не сохранены. Запустите без --dry-run, чтобы применить.');
        } else {
            $io->success('Контент загружен.');
        }

        return Command::SUCCESS;
    }

    /**
     * Импортирует раздел и печатает его отчёт. false — в разделе ошибки.
     */
    private function import(SymfonyStyle $io, string $class, bool $dryRun): bool
    {
        $result = $this->importer->import($class, $dryRun);

        $io->section($result->name);

        $rows = [];
        foreach ($result->created as $record) {
            $rows[] = ['<info>+ создать</info>', $record, ''];
        }
        foreach ($result->updated as $record => $fields) {
            $rows[] = ['<comment>~ обновить</comment>', $record, implode(', ', $fields)];
        }
        foreach ($result->missing as $record) {
            $rows[] = ['<fg=gray>? нет в контенте</>', $record, 'остаётся в базе'];
        }
        $rows === [] ? $io->text('Без изменений') : $io->table(['Действие', 'Запись', 'Поля'], $rows);

        if ($result->warnings !== []) {
            $io->warning($result->warnings);
        }
        if ($result->hasErrors()) {
            $io->error(['Ошибки в разделе, он не изменён; следующие разделы не запускались:', ...$result->errors]);
        }

        return !$result->hasErrors();
    }
}
