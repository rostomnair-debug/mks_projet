<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ContactRequestRepository;
use App\Form\AccountProfileType;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
        #[Autowire('%app.profile_uploads_dir%')] string $uploadsDir
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        $activeTab = (string) $request->query->get('tab', 'profile');
        $allowedTabs = ['profile', 'requests', 'events'];
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

        return $this->render('account/profile.html.twig', [
            'profileForm' => $form->createView(),
            'contactRequests' => $contactRequestRepository->findBy(['user' => $user], ['createdAt' => 'DESC']),
            'activeTab' => $activeTab,
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
        $reservations = $reservationRepository->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('account/reservations.html.twig', [
            'reservations' => $reservations,
            'contactRequests' => $contactRequestRepository->findBy(['user' => $this->getUser()], ['createdAt' => 'DESC']),
        ]);
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
    public function becomeAnnouncer(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('become_announcer', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isGranted('ROLE_ADMIN')) {
            $user->setRoles(['ROLE_ANNOUNCER']);
            $entityManager->flush();
            $this->addFlash('success', 'Vous êtes maintenant annonceur.');
        }

        return $this->redirectToRoute('account_profile');
    }
}
