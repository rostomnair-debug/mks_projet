<?php

namespace App\Controller;

use App\Entity\ContactRequest;
use App\Entity\Event;
use App\Form\ContactFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'contact')]
    public function index(Request $request, MailerInterface $mailer, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $target = (string) $request->request->get('target', $request->query->get('target', ''));
        $eventId = (int) $request->request->get('eventId', $request->query->get('eventId', 0));
        $requestedQuantity = (int) $request->request->get('requestedQuantity', $request->query->get('requestedQuantity', 0));
        $event = $eventId > 0 ? $entityManager->getRepository(Event::class)->find($eventId) : null;
        $isGroupRequest = $event instanceof Event || $requestedQuantity > 0;
        $defaults = [
            'name' => '',
            'email' => '',
            'subject' => '',
            'message' => '',
        ];

        if ($user instanceof \App\Entity\User) {
            $name = trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? ''));
            $defaults['name'] = $name !== '' ? $name : $user->getUsername();
            $defaults['email'] = $user->getEmail();
        }

        if ($isGroupRequest) {
            $defaults['requestedQuantity'] = $requestedQuantity > 0 ? $requestedQuantity : 7;
            if ($event instanceof Event) {
                $defaults['subject'] = 'Demande de réservation +6 places - ' . $event->getTitle();
                $defaults['message'] = "Bonjour l'équipe MKS,\n\nJe souhaite réserver pour un groupe à l'événement : "
                    . $event->getTitle()
                    . ".\n\nMerci.";
            } else {
                $defaults['subject'] = 'Demande de réservation +6 places';
                $defaults['message'] = "Bonjour l'équipe MKS,\n\nJe souhaite réserver plus de 6 places pour un événement. Pouvez-vous m'aider ?\n\nMerci.";
            }
        }

        $form = $this->createForm(ContactFormType::class, $defaults, [
            'include_quantity' => $isGroupRequest,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $quantity = (int) ($data['requestedQuantity'] ?? 0);
            if ($isGroupRequest) {
                if ($quantity < 7) {
                    $this->addFlash('error', 'Merci de demander au minimum 7 places.');

                    return $this->redirectToRoute('contact', [
                        'eventId' => $eventId,
                        'requestedQuantity' => $requestedQuantity,
                        'target' => $target,
                    ]);
                }

                if ($event instanceof Event && $event->getRemainingPlaces() < $quantity) {
                    $this->addFlash('error', 'Places insuffisantes pour cet événement.');

                    return $this->redirectToRoute('contact', [
                        'eventId' => $eventId,
                        'requestedQuantity' => $requestedQuantity,
                        'target' => $target,
                    ]);
                }
            } else {
                $quantity = 0;
            }

            $email = (new Email())
                ->from($data['email'])
                ->to('contact@mks.local')
                ->subject($data['subject'])
                ->text($data['name'] . "\n\n" . $data['message']);
            $mailer->send($email);

            $contactRequest = new ContactRequest();
            $contactRequest
                ->setName($data['name'])
                ->setEmail($data['email'])
                ->setSubject($data['subject'])
                ->setMessage($data['message'])
                ->setTargetUrl($target)
                ->setStatus('pending')
                ->setRequestedQuantity($quantity);

            if ($event instanceof Event) {
                $contactRequest->setEvent($event);
            }

            if ($user instanceof \App\Entity\User) {
                $contactRequest->setUser($user);
            }

            $entityManager->persist($contactRequest);
            $entityManager->flush();

            $this->addFlash('success', 'Message envoyé. Votre demande est en attente de réponse.');

            if ($this->isSafeTarget($target)) {
                return $this->redirect($target);
            }

            return $this->redirectToRoute('contact');
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form->createView(),
            'target' => $target,
            'event' => $event,
        ]);
    }

    private function isSafeTarget(string $target): bool
    {
        if ($target === '') {
            return false;
        }

        if (str_starts_with($target, '//') || str_contains($target, '://')) {
            return false;
        }

        return str_starts_with($target, '/');
    }
}
