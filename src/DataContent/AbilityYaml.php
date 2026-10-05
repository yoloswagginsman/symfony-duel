<?php

namespace App\DataContent;

use App\Contract\DataContent\DataContentInterface;
use App\Model\CreateAbilityModel;
use App\Repository\AbilityRepository;
use App\Service\Content\ContentStorage;
use App\Service\Content\Output\ContentImportResult;
use App\Service\AbilityService;

/**
 * Способности из data/content/abilities.yaml.
 * Отдельного slug в YAML нет — им служит "type" (он же имя способности).
 */
readonly class AbilityYaml implements DataContentInterface
{
    public function __construct(
        private ContentStorage $storage,
        private AbilityRepository $abilityRepository,
        private AbilityService $abilityService,
    ) {
    }

    public function getName(): string
    {
        return 'abilities';
    }

    public function import(ContentImportResult $result): void
    {
        foreach ($this->storage->loadReference($this->getName()) as $item) {
            $model = new CreateAbilityModel(
                slug: $item['type'],
                name: $item['type'],
                description: $item['description'] ?? null,
            );

            $ability = $this->abilityRepository->findOneBy(['slug' => $model->slug]);
            $ability === null
                ? $this->abilityService->create($model)
                : $this->abilityService->update($ability, $model);
        }
    }
}
