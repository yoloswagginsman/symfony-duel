<?php

namespace App\Command;

use App\Entity\Deck;
use App\Game\Ai\Simulator;
use App\Game\Enum\CardKind;
use App\Game\Factory\CardDefinitionFactory;
use App\Repository\CardRepository;
use App\Repository\DeckRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:game:simulate',
    description: 'Баланс базовых колод: компьютер играет каждой колодой против каждой',
    help: <<<'HELP'
        Каждая пара базовых колод играет --games партий: половину первой ходит одна колода, половину — другая.
        Партии — в памяти, база только читается. Зерно партий фиксированное — результат повторяется.

          <info>php %command.full_name%</info>
          <info>php %command.full_name% --games=50</info>
        HELP,
)]
final readonly class GameSimulateCommand
{
    // Партия без победителя за столько ходов — ничья (например, обе колоды кончились, а поле заперто)
    private const int MAX_TURNS = 200;

    public function __construct(
        private Simulator $simulator,
        private DeckRepository $deckRepository,
        private CardRepository $cardRepository,
        private CardDefinitionFactory $cardDefinitionFactory,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Партий на каждую пару колод')]
        int $games = 20,
    ): int {
        $decks = $this->deckRepository->findBy(['owner' => null], ['id' => 'ASC']);
        if (count($decks) < 2) {
            $io->error('Нужно хотя бы две базовые колоды: php bin/console app:content:import');

            return Command::FAILURE;
        }
        $buildings = array_map($this->cardDefinitionFactory->fromCard(...), $this->cardRepository->findByCardTypeSlug(CardKind::Building->value));
        $tokens = array_map($this->cardDefinitionFactory->fromCard(...), $this->cardRepository->findByCardTypeSlug(CardKind::Item->value));

        $io->title(sprintf('Симуляция: %d партий на пару колод', $games));

        $rows = [];
        $wins = array_fill_keys(array_map(static fn (Deck $deck) => $deck->getName(), $decks), [0, 0]);
        foreach ($decks as $i => $deckA) {
            foreach (array_slice($decks, $i + 1) as $deckB) {
                [$aWins, $bWins, $draws, $turns, $firstWins] = [0, 0, 0, 0, 0];

                for ($game = 0; $game < $games; ++$game) {
                    // Чётные партии первой ходит A, нечётные — B
                    $aFirst = $game % 2 === 0;
                    [$first, $second] = $aFirst ? [$deckA, $deckB] : [$deckB, $deckA];
                    $result = $this->simulator->play(
                        $this->cardDefinitionFactory->fromDeck($first),
                        $this->cardDefinitionFactory->fromDeck($second),
                        $buildings,
                        seed: $game,
                        maxTurns: self::MAX_TURNS,
                        tokens: $tokens,
                    );

                    $turns += $result['turns'];
                    match ($result['winner']) {
                        null => ++$draws,
                        0 => $aFirst ? ++$aWins : ++$bWins,
                        1 => $aFirst ? ++$bWins : ++$aWins,
                    };
                    $firstWins += $result['winner'] === 0 ? 1 : 0;
                }

                $wins[$deckA->getName()][0] += $aWins;
                $wins[$deckA->getName()][1] += $games;
                $wins[$deckB->getName()][0] += $bWins;
                $wins[$deckB->getName()][1] += $games;
                $rows[] = [
                    sprintf('%s — %s', $deckA->getName(), $deckB->getName()),
                    sprintf('%d : %d', $aWins, $bWins),
                    $draws,
                    $this->percent($firstWins, $games),
                    round($turns / $games, 1),
                ];
            }
        }

        $io->table(['Пара колод', 'Победы', 'Ничьи', 'Победы первого хода', 'Ходов в среднем'], $rows);
        $io->table(
            ['Колода', 'Побед всего'],
            array_map(fn (string $name, array $stat) => [$name, $this->percent(...$stat)], array_keys($wins), $wins),
        );
        $io->note('Компьютер жадный (на один ход вперёд) — это оценка колод, а не сильной игры.');

        return Command::SUCCESS;
    }

    private function percent(int $part, int $total): string
    {
        return $total > 0 ? sprintf('%d%%', round(100 * $part / $total)) : '—';
    }
}
