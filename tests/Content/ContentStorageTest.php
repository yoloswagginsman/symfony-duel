<?php

namespace App\Tests\Content;

use App\Service\Content\ContentStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Раскладка карт по файлам cards/<тип>/<раса>.yaml. Работает во временной папке.
 */
final class ContentStorageTest extends TestCase
{
    private string $directory;
    private Filesystem $filesystem;
    private ContentStorage $storage;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/content-storage-' . bin2hex(random_bytes(4));
        $this->storage = new ContentStorage($this->directory, 'cards', $this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testCardsAreSplitByTypeAndRace(): void
    {
        $this->storage->saveCards([
            $this->card('A', 'creature', 'elves'),
            $this->card('B', 'spell', 'undead'),
            $this->card('C', 'creature', 'elves'),
            $this->card('D', 'building', 'neutral'),
        ]);

        self::assertSame(
            ['building/neutral.yaml' => ['D'], 'creature/elves.yaml' => ['A', 'C'], 'spell/undead.yaml' => ['B']],
            array_map(static fn (array $cards) => array_column($cards, 'vendorCode'), $this->storage->loadCardFiles()),
        );
        self::assertSame(['D', 'A', 'C', 'B'], array_column($this->storage->loadCards(), 'vendorCode'), 'файлы по алфавиту');
    }

    public function testChangedRaceMovesCardAndEmptyFileIsRemoved(): void
    {
        $this->storage->saveCards([$this->card('A', 'creature', 'elves'), $this->card('B', 'creature', 'orcs')]);

        $this->storage->saveCards([$this->card('A', 'creature', 'orcs'), $this->card('B', 'creature', 'orcs')]);

        self::assertSame(['creature/orcs.yaml'], array_keys($this->storage->loadCardFiles()));
        self::assertFileDoesNotExist($this->directory . '/cards/creature/elves.yaml');
    }

    public function testCardWithoutRaceIsKeptInUnknownFile(): void
    {
        $this->storage->saveCards([['vendorCode' => 'X', 'name' => 'X', 'type' => 'spell']]);

        self::assertSame(['spell/unknown.yaml'], array_keys($this->storage->loadCardFiles()));
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $code, string $type, string $race): array
    {
        return ['vendorCode' => $code, 'name' => $code, 'race' => $race, 'type' => $type];
    }
}
