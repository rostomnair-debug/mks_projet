<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Favorite;
use App\Repository\FavoriteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/favorites')]
#[IsGranted('ROLE_USER')]
class FavoriteController extends AbstractController
{
    #[Route('/toggle/{id}', name: 'favorite_toggle', methods: ['POST'])]
    public function toggle(
        Event $event,
        Request $request,
        FavoriteRepository $favoriteRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('favorite_event_' . $event->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('event_show', ['slug' => $event->getSlug()]);
        }

        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        $existing = $favoriteRepository->findOneBy(['user' => $user, 'event' => $event]);
        if ($existing) {
            $entityManager->remove($existing);
            $entityManager->flush();
            $this->addFlash('success', 'Retiré des favoris.');
        } else {
            $favorite = new Favorite();
            $favorite->setUser($user);
            $favorite->setEvent($event);
            $entityManager->persist($favorite);
            $entityManager->flush();
            $this->addFlash('success', 'Ajouté aux favoris.');
        }

        $target = (string) $request->request->get('target', '');
        if ($this->isSafeTarget($target)) {
            return $this->redirect($target);
        }

        return $this->redirectToRoute('event_show', ['slug' => $event->getSlug()]);
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
