<?php

namespace App\DataFixtures;

use App\Entity\Tags;
use App\Enum\TagCategory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Yaml\Yaml;

class TagFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['reference'];
    }
    public function load(ObjectManager $manager): void
    {
        $data = Yaml::parseFile(__DIR__ . '/Data/tags.yaml');

        foreach ($data['tags'] as $item) {
            $tag = new Tags();
            $tag->setName($item['name'])
                ->setSlug($item['slug'])
                ->setCategory(TagCategory::from($item['category']))
                ->setIcon($item['icon'])
                ->setColorHex($item['color_hex'])
                ->setIsActive(true)
                ->setCreatedAt(new \DateTime());
            $manager->persist($tag);
        }

        $manager->flush();
    }
}
