<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Event;
use App\Repository\ContactRequestRepository;
use App\Form\AccountProfileType;
use App\Form\EventType;
use App\Repository\EventRepository;
use App\Repository\FavoriteRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\PexelsImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class AccountController extends AbstractController
{
    #[Route('/account/profile', name: 'account_profile')]
    #[IsGranted('ROLE_USER')]
    public function profile(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ContactRequestRepository $contactRequestRepository,
        ReservationRepository $reservationRepository,
        EventRepository $eventRepository,
        FavoriteRepository $favoriteRepository,
        PexelsImageService $pexelsImageService,
        SluggerInterface $slugger,
        #[Autowire('%app.profile_uploads_dir%')] string $uploadsDir,
        #[Autowire('%app.event_uploads_dir%')] string $eventUploadsDir
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        $activeTab = (string) $request->query->get('tab', 'profile');
        $allowedTabs = ['profile', 'reservations', 'requests', 'events', 'favorites'];
        if (!in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'profile';
        }
        if ($activeTab === 'events' && !$this->isGranted('ROLE_ANNOUNCER')) {
            $activeTab = 'profile';
        }

        $form = $this->createForm(AccountProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $user->getEmail();
            $existing = $userRepository->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');

                return $this->redirectToRoute('account_profile');
            }

            $username = $user->getUsername();
            $existingUsername = $userRepository->findOneBy(['username' => $username]);
            if ($existingUsername && $existingUsername->getId() !== $user->getId()) {
                $this->addFlash('error', 'Ce pseudo est déjà utilisé.');

                return $this->redirectToRoute('account_profile');
            }

            $plainPassword = (string) $form->get('plainPassword')->getData();
            if ($plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            /** @var UploadedFile|null $imageFile */
            $imageFile = $form->get('profileImageFile')->getData();
            if ($imageFile) {
                $safeName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower($safeName));
                $newFilename = $safeName . '-' . uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($uploadsDir, $newFilename);
                $user->setProfileImage('uploads/avatars/' . $newFilename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('account_profile');
        }

        $announcerEvents = [];
        $eventFormView = null;
        if ($this->isGranted('ROLE_ANNOUNCER')) {
            $announcerEvents = $eventRepository->findBy(
                ['organizer' => $user],
                ['startAt' => 'DESC']
            );

            $event = new Event();
            $event->setOrganizer($user);
            $eventForm = $this->createForm(EventType::class, $event);
            $eventForm->handleRequest($request);

            if ($eventForm->isSubmitted() && $eventForm->isValid()) {
                if ($event->getCapacity() < $event->getReservedCount()) {
                    $eventForm->addError(new FormError('La capacité ne peut pas être inférieure aux places déjà réservées.'));
                } else {
                    $event->setIsPublished($request->request->has('publish'));
                    $this->setUniqueSlug($event, $eventRepository, $slugger);
                    $event->setUpdatedAt(new DateTimeImmutable());
                    $this->handleImageUpload($eventForm->get('imageFile')->getData(), $event, $slugger, $eventUploadsDir);
                    $pexelsImageService->attachImageForEvent($event);
                    $entityManager->persist($event);
                    $entityManager->flush();
                    $this->addFlash('success', 'Annonce créée.');

                    return $this->redirectToRoute('account_profile', ['tab' => 'events']);
                }
            }

            $eventFormView = $eventForm->createView();
        }

        $favorites = $favoriteRepository->findByUserWithEvents($user);

        return $this->render('account/profile.html.twig', [
            'profileForm' => $form->createView(),
            'contactRequests' => $contactRequestRepository->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'reservations' => $reservationRepository->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'announcerEvents' => $announcerEvents,
            'eventForm' => $eventFormView,
            'activeTab' => $activeTab,
            'favorites' => $favorites,
        ]);
    }

    #[Route('/account/profile/events', name: 'account_profile_events')]
    #[IsGranted('ROLE_ANNOUNCER')]
    public function profileEvents(): Response
    {
        return $this->redirectToRoute('account_profile', ['tab' => 'events']);
    }

    #[Route('/account/reservations', name: 'account_reservations')]
    #[IsGranted('ROLE_USER')]
    public function reservations(ReservationRepository $reservationRepository, ContactRequestRepository $contactRequestRepository): Response
    {
        return $this->redirectToRoute('account_profile', ['tab' => 'reservations']);
    }

    #[Route('/reservations/{id}/cancel', name: 'reservation_cancel', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function cancel(Reservation $reservation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('cancel_reservation_' . $reservation->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $event = $reservation->getEvent();
        if ($event->getStartAt() <= new DateTimeImmutable()) {
            $this->addFlash('error', 'Annulation impossible pour un événement passé.');

            return $this->redirectToRoute('account_reservations');
        }

        if (!$reservation->isCancelled()) {
            $reservation->cancel();
            $event->decrementReservedCount($reservation->getQuantity());
            $entityManager->flush();
        }

        $this->addFlash('success', 'Réservation annulée.');

        return $this->redirectToRoute('account_reservations');
    }

    #[Route('/account/become-announcer', name: 'account_become_announcer', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function becomeAnnouncer(Request $request, EntityManagerInterface $entityManager, Security $security): Response
    {
        if (!$this->isCsrfTokenValid('become_announcer', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $roles = $user->getRoles();
            $roles[] = 'ROLE_USER';
            $roles[] = 'ROLE_ANNOUNCER';
            $user->setRoles(array_values(array_unique($roles)));
            $entityManager->flush();
            $security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Vous êtes maintenant annonceur.');
        }

        return $this->redirectToRoute('account_profile');
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

        $safeName = $slugger->slug(pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME))->lower();
        $newFilename = $safeName . '-' . uniqid() . '.' . $imageFile->guessExtension();
        $imageFile->move($uploadsDir, $newFilename);
        $event->setImagePath('uploads/events/' . $newFilename);
    }
}
