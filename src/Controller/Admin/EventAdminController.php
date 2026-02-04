<?php

namespace App\Controller\Admin;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Service\AmpMetropoleImporter;
use App\Service\PexelsImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/events')]
class EventAdminController extends AbstractController
{
    #[Route('/', name: 'admin_event_index')]
    public function index(Request $request, EventRepository $eventRepository): Response
    {
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'events'] + $request->query->all());
    }

    #[Route('/new', name: 'admin_event_new')]
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

                return $this->render('admin/event/new.html.twig', [
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

            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('admin/event/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_event_edit')]
    public function edit(
        Request $request,
        Event $event,
        EventRepository $eventRepository,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        PexelsImageService $pexelsImageService,
        #[Autowire('%app.event_uploads_dir%')] string $uploadsDir
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN') && $event->getOrganizer() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($event->getCapacity() < $event->getReservedCount()) {
                $form->addError(new FormError('La capacité ne peut pas être inférieure aux places déjà réservées.'));

                return $this->render('admin/event/edit.html.twig', [
                    'event' => $event,
                    'form' => $form->createView(),
                ]);
            }

            $event->setIsPublished($request->request->has('publish'));
            $this->setUniqueSlug($event, $eventRepository, $slugger);
            $event->setUpdatedAt(new DateTimeImmutable());
            $this->handleImageUpload($form->get('imageFile')->getData(), $event, $slugger, $uploadsDir);
            $pexelsImageService->attachImageForEvent($event);

            $entityManager->flush();

            $this->addFlash('success', 'Événement mis à jour.');

            return $this->redirectToRoute('admin_event_index');
        }

        return $this->render('admin/event/edit.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_event_delete', methods: ['POST'])]
    public function delete(Request $request, Event $event, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && $event->getOrganizer() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($event->getReservations()->count() > 0) {
            $this->addFlash('error', 'Suppression impossible : des réservations existent déjà.');

            return $this->redirectToRoute('admin_event_index');
        }

        if ($this->isCsrfTokenValid('delete_event_' . $event->getId(), $request->request->get('_token'))) {
            $entityManager->remove($event);
            $entityManager->flush();
            $this->addFlash('success', 'Événement supprimé.');
        }

        return $this->redirectToRoute('admin_event_index');
    }

    #[Route('/import/amp', name: 'admin_event_import_amp', methods: ['POST'])]
    public function importAmp(Request $request, AmpMetropoleImporter $importer): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('import_amp', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('admin_event_index');
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        try {
            $result = $importer->import($user, 20);

            $this->addFlash(
                'success',
                sprintf(
                    'Import terminé. %d créé(s), %d maj, %d ignoré(s).',
                    $result['created'],
                    $result['updated'],
                    $result['skipped']
                )
            );
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'Import impossible : ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_event_index');
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

    private function handleImageUpload(?UploadedFile $imageFile, Event $event, SluggerInterface $slugger, string $uploadsDir): void
    {
        if (!$imageFile) {
            return;
        }

        $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename)->lower();
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $imageFile->guessExtension();

        $imageFile->move($uploadsDir, $newFilename);

        $event->setImagePath('uploads/events/' . $newFilename);
    }
}
