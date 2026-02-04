<?php

namespace App\Controller;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Service\PexelsImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/account/events')]
#[IsGranted('ROLE_ANNOUNCER')]
class AccountEventController extends AbstractController
{
    #[Route('/', name: 'account_event_index')]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        return $this->redirectToRoute('account_profile', ['tab' => 'events']);
    }

    #[Route('/new', name: 'account_event_new')]
    public function new(
        Request $request,
        EventRepository $eventRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        PexelsImageService $pexelsImageService,
        #[Autowire('%app.event_uploads_dir%')] string $uploadsDir
    ): Response {
        $event = new Event();
        $organizer = $this->getUser();
        if (!$organizer instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }
        $event->setOrganizer($organizer);

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($event->getCapacity() < $event->getReservedCount()) {
                $form->addError(new FormError('La capacité ne peut pas être inférieure aux places déjà réservées.'));

                return $this->render('account/event/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $event->setIsPublished($request->request->has('publish'));
            $this->setUniqueSlug($event, $eventRepository, $slugger);
            $event->setUpdatedAt(new DateTimeImmutable());
            $this->handleImageUpload($form->get('imageFile')->getData(), $event, $slugger, $uploadsDir);
            $pexelsImageService->attachImageForEvent($event);

            $entityManager->persist($event);
            $entityManager->flush();

            $this->addFlash('success', 'Événement créé avec succès.');

            return $this->redirectToRoute('account_profile', ['tab' => 'events']);
        }

        return $this->render('account/event/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'account_event_edit')]
    public function edit(
        Request $request,
        Event $event,
        EventRepository $eventRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        PexelsImageService $pexelsImageService,
        #[Autowire('%app.event_uploads_dir%')] string $uploadsDir
    ): Response {
        if ($event->getOrganizer() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($event->getCapacity() < $event->getReservedCount()) {
                $form->addError(new FormError('La capacité ne peut pas être inférieure aux places déjà réservées.'));

                return $this->render('account/event/edit.html.twig', [
                    'form' => $form->createView(),
                    'event' => $event,
                ]);
            }

            $event->setIsPublished($request->request->has('publish'));
            $this->setUniqueSlug($event, $eventRepository, $slugger);
            $event->setUpdatedAt(new DateTimeImmutable());
            $this->handleImageUpload($form->get('imageFile')->getData(), $event, $slugger, $uploadsDir);
            $pexelsImageService->attachImageForEvent($event);

            $entityManager->flush();

            $this->addFlash('success', 'Événement mis à jour.');

            return $this->redirectToRoute('account_event_index');
        }

        return $this->render('account/event/edit.html.twig', [
            'form' => $form->createView(),
            'event' => $event,
        ]);
    }

    private function setUniqueSlug(Event $event, EventRepository $eventRepository, SluggerInterface $slugger): void
    {
        $baseSlug = $slugger->slug($event->getTitle())->lower()->toString();
        $slug = $baseSlug;
        $suffix = 1;

        while ($existing = $eventRepository->findOneBy(['slug' => $slug])) {
            if ($existing->getId() === $event->getId()) {
                break;
            }
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        $event->setSlug($slug);
    }

    private function handleImageUpload(?\Symfony\Component\HttpFoundation\File\UploadedFile $imageFile, Event $event, SluggerInterface $slugger, string $uploadsDir): void
    {
        if (!$imageFile) {
            return;
        }

        $safeName = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME))->lower();
        $newFilename = $safeName . '-' . uniqid() . '.' . $imageFile->guessExtension();
        $imageFile->move($uploadsDir, $newFilename);
        $event->setImagePath('uploads/events/' . $newFilename);
    }
}
