<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(EventRepository $eventRepository, CategoryRepository $categoryRepository): Response
    {
        $events = $eventRepository->searchPublic([]);
        $events = array_slice($events, 0, 6);

        $mosaicCategories = $categoryRepository->findWithAvailableEvents();
        $priority = ['musée', 'expo', 'festival', 'concert', 'atelier'];
        $toLower = static fn (string $value): string => function_exists('mb_strtolower')
            ? mb_strtolower($value)
            : strtolower($value);
        $priorityIndex = array_flip($priority);

        usort($mosaicCategories, static function ($left, $right) use ($priorityIndex, $toLower): int {
            $leftName = $toLower($left->getName());
            $rightName = $toLower($right->getName());
            $leftRank = $priorityIndex[$leftName] ?? PHP_INT_MAX;
            $rightRank = $priorityIndex[$rightName] ?? PHP_INT_MAX;

            if ($leftRank === $rightRank) {
                return $leftName <=> $rightName;
            }

            return $leftRank <=> $rightRank;
        });

        return $this->render('home/index.html.twig', [
            'events' => $events,
            'categories' => $categoryRepository->findAll(),
            'mosaicCategories' => $mosaicCategories,
        ]);
    }
}
