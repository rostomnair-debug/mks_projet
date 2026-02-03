<?php

namespace App\Controller\Admin;

use App\Entity\ContactRequest;
use App\Entity\Event;
use App\Entity\Reservation;
use App\Form\ContactResponseType;
use App\Repository\ContactRequestRepository;
use DateTimeImmutable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contacts')]
#[IsGranted('ROLE_ADMIN')]
class ContactAdminController extends AbstractController
{
    #[Route('/', name: 'admin_contact_index')]
    public function index(Request $request, ContactRequestRepository $repository): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $pagination = $repository->findPaginated($page, 12);
        $query = $request->query->all();
        unset($query['page']);

        return $this->render('admin/contact/index.html.twig', [
            'requests' => $pagination['items'],
            'pagination' => $pagination,
            'query' => $query,
        ]);
    }

    #[Route('/{id}', name: 'admin_contact_show')]
    public function show(
        ContactRequest $contactRequest,
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $form = $this->createForm(ContactResponseType::class, $contactRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contactRequest->setStatus('answered');
            $contactRequest->setRespondedAt(new DateTimeImmutable());
            $entityManager->flush();

            $email = (new Email())
                ->from('contact@mks.local')
                ->to($contactRequest->getEmail())
                ->subject('Re: ' . $contactRequest->getSubject())
                ->text($contactRequest->getAdminResponse() ?? '');
            $mailer->send($email);

            $this->addFlash('success', 'Réponse envoyée.');

            return $this->redirectToRoute('admin_contact_show', ['id' => $contactRequest->getId()]);
        }

        return $this->render('admin/contact/show.html.twig', [
            'request' => $contactRequest,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/confirm', name: 'admin_contact_confirm', methods: ['POST'])]
    public function confirm(
        ContactRequest $contactRequest,
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if (!$this->isCsrfTokenValid('confirm_contact_' . $contactRequest->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('admin_contact_index');
        }

        $event = $contactRequest->getEvent();
        $user = $contactRequest->getUser();
        $quantity = $contactRequest->getRequestedQuantity();

        if (!$event instanceof Event || !$user) {
            $this->addFlash('error', 'Impossible de confirmer : événement ou utilisateur manquant.');

            return $this->redirectToRoute('admin_contact_index');
        }

        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $lockedEvent = $entityManager->find(Event::class, $event->getId(), LockMode::PESSIMISTIC_WRITE);
            if (!$lockedEvent || !$lockedEvent->canReserve($quantity)) {
                throw new \RuntimeException('Places insuffisantes.');
            }

            $reservation = new Reservation();
            $reservation->setEvent($lockedEvent);
            $reservation->setUser($user);
            $reservation->setQuantity($quantity);
            $lockedEvent->incrementReservedCount($quantity);

            $contactRequest->setStatus('confirmed');
            $contactRequest->setRespondedAt(new DateTimeImmutable());

            $entityManager->persist($reservation);
            $entityManager->flush();
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('admin_contact_index');
        }

        $email = (new Email())
            ->from('contact@mks.local')
            ->to($contactRequest->getEmail())
            ->subject('Confirmation de votre demande')
            ->text("Votre demande pour l'événement \"" . $event->getTitle() . "\" a été confirmée.\n\nMerci pour votre confiance.\nL'équipe MKS");
        $mailer->send($email);

        $this->addFlash('success', 'Demande confirmée et réservation créée.');

        return $this->redirectToRoute('admin_contact_index');
    }
}
