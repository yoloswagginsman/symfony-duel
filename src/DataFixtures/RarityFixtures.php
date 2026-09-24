<?php

namespace App\DataFixtures;

use App\Entity\Rarity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Yaml\Yaml;

class RarityFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['reference'];
    }
    public function load(ObjectManager $manager): void
    {
        $data = Yaml::parseFile(__DIR__ . '/Data/rarities.yaml');

        foreach ($data['rarities'] as $item) {
            $rarity = new Rarity();
            $rarity->setName($item['name'])
                ->setSlug($item['slug'])
                ->setColorHex($item['color_hex'])
                ->setDropChance($item['drop_chance']);
            $manager->persist($rarity);
        }

        $manager->flush();
    }
}