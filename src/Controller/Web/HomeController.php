<?php

namespace App\Controller\Web;

use App\Repository\CardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(CardRepository $cardsRepository): Response
    {
        $latestCards = $cardsRepository->findBy([], ['createdAt' => 'DESC'], 8);
        $totalCards = $cardsRepository->count();
        $rarityStats = $cardsRepository->countByRarity();

        return $this->render('home/index.html.twig', [
            'latestCards' => $latestCards,
            'totalCards' => $totalCards,
            'rarityStats' => $rarityStats,
        ]);
    }
}