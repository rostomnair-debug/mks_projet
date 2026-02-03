<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReservationController extends AbstractController
{
    #[Route('/events/{id}/reserve', name: 'reservation_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reserve(Event $event, Request $request, EntityManagerInterface $entityManager, ReservationRepository $reservationRepository): Response
    {
        if (!$event->isPublished()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('reserve' . $event->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $quantity = max(1, (int) $request->request->get('quantity', 1));
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $lockedEvent = $entityManager->find(Event::class, $event->getId(), LockMode::PESSIMISTIC_WRITE);

            if (!$lockedEvent || !$lockedEvent->canReserve($quantity)) {
                throw new \RuntimeException('Plus de places disponibles.');
            }

            $alreadyReserved = $reservationRepository->getReservedCountForUserEvent($user, $lockedEvent);
            if ($alreadyReserved + $quantity > 6) {
                throw new \RuntimeException('Limite atteinte : 6 places max par utilisateur pour cet événement.');
            }

            $reservation = new Reservation();
            $reservation->setEvent($lockedEvent);
            $reservation->setUser($user);
            $reservation->setQuantity($quantity);

            $lockedEvent->incrementReservedCount($quantity);

            $entityManager->persist($reservation);
            $entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('event_show', ['slug' => $event->getSlug()]);
        }

        $this->addFlash('success', 'Réservation confirmée.');

        return $this->redirectToRoute('account_reservations');
    }
}
