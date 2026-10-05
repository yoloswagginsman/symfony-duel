<?php

namespace App\DataFixtures;

use App\Entity\Race;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Yaml\Yaml;

class RaceFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['reference'];
    }
    public function load(ObjectManager $manager): void
    {
        $data = Yaml::parseFile(__DIR__ . '/Data/races.yaml');

        foreach ($data['races'] as $item) {
            $race = new Race();
            $race->setName($item['name'])
                ->setSlug($item['slug'])
                ->setDescription($item['description']);
            $manager->persist($race);
        }

        $manager->flush();
    }
}
