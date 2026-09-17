<?php

namespace App\DataFixtures;

use App\Dto\CreateCardFixtureDto;
use App\Entity\Ability;
use App\Entity\CardAbility;
use App\Entity\Card;
use App\Entity\CardType;
use App\Entity\Race;
use App\Entity\Rarity;
use App\Entity\Tag;
use App\Service\CardService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Yaml\Yaml;

class CardFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function __construct(
        private readonly CardService $cardService,
    ) {
    }
    public static function getGroups(): array
    {
        return ['cards'];
    }
    public function load(ObjectManager $manager): void
    {
        $data = Yaml::parseFile(__DIR__ . '/Data/cards.yaml');

        $raceRepo = $manager->getRepository(Race::class);
        $typeRepo = $manager->getRepository(CardType::class);
        $rarityRepo = $manager->getRepository(Rarity::class);
        $tagRepo = $manager->getRepository(Tag::class);
        $abilityRepo = $manager->getRepository(Ability::class);

        foreach ($data['cards'] as $item) {

            // 1. Собираем теги
            $tags = [];
            foreach ($item['tags'] ?? [] as $slug) {
                if ($tag = $tagRepo->findOneBy(['slug' => $slug])) {
                    $tags[] = $tag;
                }
            }

            // 2. Собираем способности
            $abilities = [];
            if (isset($item['abilities']) && is_array($item['abilities'])) {
                foreach ($item['abilities'] as $abilityData) {
                    $name = is_array($abilityData) ? ($abilityData['name'] ?? null) : $abilityData;
                    $value = is_array($abilityData) ? ($abilityData['value'] ?? null) : null;

                    if ($name && $ability = $abilityRepo->findOneBy(['name' => $name])) {
                        $abilities[] = ['ability' => $ability, 'value' => $value];
                    }
                }
            }

            // 3. Создаем Fixture DTO
            $fixtureDto = new CreateCardFixtureDto(
                name: $item['name'],
                description: $item['description'] ?? null,
                manaCost: $item['manaCost'],
                attack: $item['attack'] ?? null,
                health: $item['health'] ?? null,
                imagePath: $item['imagePath'] ?? null,
                race: $raceRepo->findOneBy(['slug' => $item['race']]),
                cardType: $typeRepo->findOneBy(['slug' => $item['type']]),
                rarity: $rarityRepo->findOneBy(['slug' => $item['rarity']]),
                tags: $tags,
                abilities: $abilities,
            );

            // 4. Передаем в единый CardService через toModel()
            $this->cardService->create($fixtureDto->toModel());
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            RaceFixtures::class,
            CardTypeFixtures::class,
            RarityFixtures::class,
            TagFixtures::class,
            AbilityFixtures::class,
        ];
    }
}