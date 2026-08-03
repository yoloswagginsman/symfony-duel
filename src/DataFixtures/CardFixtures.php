<?php

namespace App\DataFixtures;

use App\Entity\Abilities;
use App\Entity\CardAbilities;
use App\Entity\Cards;
use App\Entity\CardTypes;
use App\Entity\Races;
use App\Entity\Rarities;
use App\Entity\Tags;
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

        $raceRepo = $manager->getRepository(Races::class);
        $typeRepo = $manager->getRepository(CardTypes::class);
        $rarityRepo = $manager->getRepository(Rarities::class);
        $tagRepo = $manager->getRepository(Tags::class);
        $abilityRepo = $manager->getRepository(Abilities::class);

        foreach ($data['cards'] as $item) {
            $card = new Cards();
            $card->setName($item['name'])
                ->setDescription($item['description'])
                ->setManaCost($item['manaCost'])
                ->setAttack($item['attack'])
                ->setHealth($item['health'])
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

            foreach ($item['abilities'] as $abilityName) {
                $ability = $abilityRepo->findOneBy(['name' => $abilityName]);
                if ($ability) {
                    $card->addAbilityWithValue($ability, 0);
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