<?php

namespace App\DataContent;

use App\Contract\DataContent\DataContentInterface;
use App\Enum\TagCategory;
use App\Model\CreateTagModel;
use App\Repository\TagRepository;
use App\Service\Content\ContentStorage;
use App\Service\Content\Output\ContentImportResult;
use App\Service\TagService;

/**
 * Теги из data/content/tags.yaml, сопоставление по slug.
 */
readonly class TagYaml implements DataContentInterface
{
    public function __construct(
        private ContentStorage $storage,
        private TagRepository $tagRepository,
        private TagService $tagService,
    ) {
    }

    public function getName(): string
    {
        return 'tags';
    }

    public function import(ContentImportResult $result): void
    {
        foreach ($this->storage->loadReference($this->getName()) as $item) {
            $model = new CreateTagModel(
                slug: $item['slug'],
                name: $item['name'],
                category: TagCategory::from($item['category']),
                colorHex: $item['color_hex'],
                icon: $item['icon'] ?? null,
            );

            $tag = $this->tagRepository->findOneBy(['slug' => $model->slug]);
            $tag === null
                ? $this->tagService->create($model)
                : $this->tagService->update($tag, $model);
        }
    }
}
