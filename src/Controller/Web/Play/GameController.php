<?php

namespace App\Controller\Web\Play;

use App\Entity\Ability;
use App\Entity\Card;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\AbilityRepository;
use App\Repository\CardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Страница партии. Поле рисует Stimulus-контроллер (assets/controllers/game_controller.js)
 * по API (/api/games/{id}) и обновляет по Mercure.
 */
#[IsGranted(UserRole::PLAYER->value)]
class GameController extends AbstractController
{
    public function __construct(
        private readonly CardRepository $cardRepository,
        private readonly AbilityRepository $abilityRepository,
    ) {
    }

    #[Route(path: '/play/{id}', name: 'app_play_game', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function show(Game $game, #[CurrentUser] User $user): Response
    {
        if ($game->playerIndexOf($user) === null) {
            throw $this->createNotFoundException();
        }

        return $this->render('play/game.html.twig', [
            'game' => $game,
            'cardInfo' => $this->cardInfo(),
            'abilityInfo' => $this->abilityInfo(),
        ]);
    }

    /**
     * Для страницы — то, чего нет в состоянии партии: картинка, описание, раса, редкость (по vendorCode).
     *
     * @return array<string, array<string, ?string>>
     */
    private function cardInfo(): array
    {
        $info = [];
        foreach ($this->cardRepository->findAll() as $card) {
            /** @var Card $card */
            $info[(string) $card->getVendorCode()] = [
                'image' => $card->getImagePath() !== null ? '/upload/cards/' . $card->getImagePath() : null,
                'description' => $card->getDescription(),
                'race' => $card->getRace()?->getName(),
                'type' => $card->getCardType()?->getName(),
                'rarity' => $card->getRarity()?->getSlug(),
                'rarityName' => $card->getRarity()?->getName(),
            ];
        }

        return $info;
    }

    /**
     * Пояснения способностей (из контента abilities.yaml) по slug.
     *
     * @return array<string, ?string>
     */
    private function abilityInfo(): array
    {
        $info = [];
        foreach ($this->abilityRepository->findAll() as $ability) {
            /** @var Ability $ability */
            $info[(string) $ability->getSlug()] = $ability->getDescription();
        }

        return $info;
    }
}
