<?php

namespace App\Controller;

use App\Entity\Event;
use App\Repository\CategoryRepository;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class EventController extends AbstractController
{
    #[Route('/events', name: 'event_index')]
    public function index(Request $request, EventRepository $eventRepository, CategoryRepository $categoryRepository): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q')),
            'category' => $request->query->get('category'),
            'district' => trim((string) $request->query->get('district')),
            'start_date' => $request->query->get('start_date'),
            'end_date' => $request->query->get('end_date'),
            'free' => $request->query->getBoolean('free'),
            'price' => $request->query->get('price'),
            'sort' => $request->query->get('sort'),
        ];

        $page = max(1, (int) $request->query->get('page', 1));
        $pagination = $eventRepository->searchPublicPaginated($filters, $page, 9);

        $query = $request->query->all();
        unset($query['page']);

        return $this->render('event/index.html.twig', [
            'events' => $pagination['items'],
            'categories' => $categoryRepository->findAll(),
            'filters' => $filters,
            'pagination' => $pagination,
            'query' => $query,
        ]);
    }

    #[Route('/events/{slug}', name: 'event_show')]
    public function show(#[MapEntity(mapping: ['slug' => 'slug'])] Event $event, ReservationRepository $reservationRepository): Response
    {
        if (!$event->isPublished() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createNotFoundException();
        }

        $userReserved = 0;
        if ($this->getUser() instanceof \App\Entity\User) {
            $userReserved = $reservationRepository->getReservedCountForUserEvent($this->getUser(), $event);
        }

        return $this->render('event/show.html.twig', [
            'event' => $event,
            'userReserved' => $userReserved,
        ]);
    }
}
