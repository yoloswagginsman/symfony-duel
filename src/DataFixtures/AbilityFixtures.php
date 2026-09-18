<?php

namespace App\DataFixtures;

use App\Entity\Ability;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Yaml\Yaml;

class AbilityFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['reference'];
    }
    public function load(ObjectManager $manager): void
    {
        $data = Yaml::parseFile(__DIR__ . '/Data/abilities.yaml');

        foreach ($data['abilities'] as $item) {
            $ability = new Ability();
            $ability->setName($item['type'])
                ->setSlug($item['type'])
                ->setIsActive(true)
                ->setDescription($item['description']);
            $manager->persist($ability);
        }

        $manager->flush();
    }
}
