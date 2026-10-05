<?php

namespace App\DataContent;

use App\Contract\DataContent\DataContentInterface;
use App\Model\CreateCardTypeModel;
use App\Repository\CardTypeRepository;
use App\Service\Content\ContentStorage;
use App\Service\Content\Output\ContentImportResult;
use App\Service\CardTypeService;

/**
 * Типы карт из data/content/card_types.yaml, сопоставление по slug.
 */
readonly class CardTypeYaml implements DataContentInterface
{
    public function __construct(
        private ContentStorage $storage,
        private CardTypeRepository $cardTypeRepository,
        private CardTypeService $cardTypeService,
    ) {
    }

    public function getName(): string
    {
        return 'card_types';
    }

    public function import(ContentImportResult $result): void
    {
        foreach ($this->storage->loadReference($this->getName()) as $item) {
            $model = new CreateCardTypeModel(
                slug: $item['slug'],
                name: $item['name'],
            );

            $cardType = $this->cardTypeRepository->findOneBy(['slug' => $model->slug]);
            $cardType === null
                ? $this->cardTypeService->create($model)
                : $this->cardTypeService->update($cardType, $model);
        }
    }
}
