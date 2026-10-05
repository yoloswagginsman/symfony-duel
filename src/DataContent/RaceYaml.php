<?php

namespace App\DataContent;

use App\Contract\DataContent\DataContentInterface;
use App\Model\CreateRaceModel;
use App\Repository\RaceRepository;
use App\Service\Content\ContentStorage;
use App\Service\Content\Output\ContentImportResult;
use App\Service\RaceService;

/**
 * Расы из data/content/races.yaml, сопоставление по slug.
 */
readonly class RaceYaml implements DataContentInterface
{
    public function __construct(
        private ContentStorage $storage,
        private RaceRepository $raceRepository,
        private RaceService $raceService,
    ) {
    }

    public function getName(): string
    {
        return 'races';
    }

    public function import(ContentImportResult $result): void
    {
        foreach ($this->storage->loadReference($this->getName()) as $item) {
            $model = new CreateRaceModel(
                slug: $item['slug'],
                name: $item['name'],
                description: $item['description'] ?? null,
            );

            $race = $this->raceRepository->findOneBy(['slug' => $model->slug]);
            $race === null
                ? $this->raceService->create($model)
                : $this->raceService->update($race, $model);
        }
    }
}
