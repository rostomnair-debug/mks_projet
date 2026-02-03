<?php

namespace App\Controller\Api;

use App\Repository\EventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends AbstractController
{
    #[Route('/api/events-map', name: 'api_events_map', methods: ['GET'])]
    public function eventsMap(EventRepository $eventRepository): JsonResponse
    {
        $events = $eventRepository->findMapPoints();
        $data = [];

        foreach ($events as $event) {
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'slug' => $event->getSlug(),
                'latitude' => $event->getLatitude(),
                'longitude' => $event->getLongitude(),
                'venue' => $event->getVenueName(),
            ];
        }

        return $this->json($data);
    }
}
