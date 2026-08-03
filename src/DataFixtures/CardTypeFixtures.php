<?php

namespace App\DataFixtures;

use App\Entity\CardTypes;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Yaml\Yaml;

class CardTypeFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['reference'];
    }
    public function load(ObjectManager $manager): void
    {
        $data = Yaml::parseFile(__DIR__ . '/Data/card_types.yaml');

        foreach ($data['card_types'] as $item) {
            $type = new CardTypes();
            $type->setName($item['name'])
                ->setSlug($item['slug']);
            $manager->persist($type);
        }

        $manager->flush();
    }
}
