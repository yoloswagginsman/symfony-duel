<?php

namespace App\EventListener;

use App\Entity\Card;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\String\Slugger\SluggerInterface;

#[AsEntityListener(event: Events::prePersist, entity: Card::class)]
readonly class CardVendorCodeListener
{
    public function __construct(
        private SluggerInterface $slugger
    ) {}

    public function prePersist(Card $card): void
    {
        if ($card->getVendorCode() !== null) {
            return;
        }

        $typePrefix = strtoupper(substr($card->getCardType()?->getSlug() ?? 'TYPE', 0, 3));
        $racePrefix = strtoupper(substr($card->getRace()?->getSlug() ?? 'RACE', 0, 3));
        $nameSlug = $card->getName() ? strtoupper($this->slugger->slug($card->getName())->toString()) : 'CARD';

        $vendorCode = sprintf(
            'DUEL-%s-%s-%s',
            $typePrefix,
            $racePrefix,
            $nameSlug
        );

        $card->setVendorCode($vendorCode);
    }
}