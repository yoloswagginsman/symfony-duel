<?php

namespace App\DataFixtures;

use App\Entity\Ability;
use App\Entity\CardAbility;
use App\Entity\Card;
use App\Entity\CardType;
use App\Entity\Race;
use App\Entity\Rarity;
use App\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Yaml\Yaml;

class CardFixtures extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
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
            $card = new Card();
            $card->setName($item['name'])
                ->setDescription($item['description'])
                ->setManaCost($item['manaCost'])
                ->setAttack($item['attack'])
                ->setHealth($item['health'])
                ->setImagePath($item['imagePath'] ?? null)
                ->setRace($raceRepo->findOneBy(['slug' => $item['race']]))
                ->setCardType($typeRepo->findOneBy(['slug' => $item['type']]))
                ->setRarity($rarityRepo->findOneBy(['slug' => $item['rarity']]))
                ->setUpdatedAt(new \DateTime());

            foreach ($item['tags'] as $slug) {
                $tag = $tagRepo->findOneBy(['slug' => $slug]);
                if ($tag) {
                    $card->addTag($tag);
                }
            }

            if (isset($item['abilities']) && is_array($item['abilities'])) {
                foreach ($item['abilities'] as $abilityData) {
                    $abilityName = null;
                    $abilityValue = null;

                    if (is_string($abilityData)) {
                        $abilityName = $abilityData;
                    } else {
                        if (is_array($abilityData)) {
                            $abilityName = $abilityData['name'] ?? null;
                            $abilityValue = $abilityData['value'] ?? null;
                        }
                    }

                    if ($abilityName) {
                        $ability = $abilityRepo->findOneBy(['name' => $abilityName]);
                        if ($ability) {
                            $card->addAbilityWithValue($ability, $abilityValue);
                        }
                    }
                }
            }


            $manager->persist($card);
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