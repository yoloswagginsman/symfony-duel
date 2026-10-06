<?php

namespace App\DataContent;

use App\Contract\DataContent\DataContentInterface;
use App\Model\CreateRarityModel;
use App\Repository\RarityRepository;
use App\Service\Content\ContentStorage;
use App\Service\Content\Output\ContentImportResult;
use App\Service\RarityService;

/**
 * Редкости из data/content/rarities.yaml, сопоставление по slug.
 */
readonly class RarityYaml implements DataContentInterface
{
    public function __construct(
        private ContentStorage $storage,
        private RarityRepository $rarityRepository,
        private RarityService $rarityService,
    ) {
    }

    public function getName(): string
    {
        return 'rarities';
    }

    public function import(ContentImportResult $result): void
    {
        foreach ($this->storage->loadReference($this->getName()) as $item) {
            $model = new CreateRarityModel(
                slug: $item['slug'],
                name: $item['name'],
                colorHex: $item['color_hex'],
                // В базе шанс целый: дробная часть из YAML (57.5) отбрасывается
                dropChance: (int) $item['drop_chance'],
            );

            $rarity = $this->rarityRepository->findOneBy(['slug' => $model->slug]);
            $rarity === null
                ? $this->rarityService->create($model)
                : $this->rarityService->update($rarity, $model);
        }
    }
}
