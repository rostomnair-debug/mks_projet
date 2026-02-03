<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Form\AdminUserType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class UserAdminController extends AbstractController
{
    #[Route('/', name: 'admin_user_index')]
    public function index(Request $request, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): Response
    {
        if ($request->isMethod('POST') && $request->request->get('action') === 'create') {
            if (!$this->isCsrfTokenValid('user_create', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Action refusée.');
            } else {
                $email = trim((string) $request->request->get('email'));
                $password = (string) $request->request->get('password');
                $username = trim((string) $request->request->get('username'));
                $role = (string) $request->request->get('role', 'ROLE_USER');

                if ($email === '' || $password === '' || $username === '') {
                    $this->addFlash('error', 'Email, pseudo et mot de passe obligatoires.');
                } elseif ($userRepository->findOneBy(['email' => $email])) {
                    $this->addFlash('error', 'Email déjà utilisé.');
                } elseif ($userRepository->findOneBy(['username' => $username])) {
                    $this->addFlash('error', 'Pseudo déjà utilisé.');
                } else {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setUsername($username);
                    $user->setRoles([$role]);
                    $user->setIsVerified(true);
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                    $userRepository->save($user, true);
                    $this->addFlash('success', 'Utilisateur créé.');
                }
            }
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $pagination = $userRepository->findPaginated($page, 12);
        $query = $request->query->all();
        unset($query['page']);

        return $this->render('admin/user/index.html.twig', [
            'users' => $pagination['items'],
            'pagination' => $pagination,
            'query' => $query,
        ]);
    }

    #[Route('/{id}/role', name: 'admin_user_role', methods: ['POST'])]
    public function updateRole(Request $request, User $user, UserRepository $userRepository): Response
    {
        if (!$this->isCsrfTokenValid('user_role_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('admin_user_index');
        }

        $role = (string) $request->request->get('role');
        $allowed = ['ROLE_USER', 'ROLE_ANNOUNCER', 'ROLE_ADMIN'];

        if (!in_array($role, $allowed, true)) {
            $this->addFlash('error', 'Rôle invalide.');

            return $this->redirectToRoute('admin_user_index');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId() && $role !== 'ROLE_ADMIN') {
            $this->addFlash('error', 'Vous ne pouvez pas retirer votre rôle admin.');

            return $this->redirectToRoute('admin_user_index');
        }

        $user->setRoles([$role]);
        $userRepository->save($user, true);

        $this->addFlash('success', 'Rôle mis à jour.');

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, UserRepository $userRepository): Response
    {
        if (!$this->isCsrfTokenValid('user_delete_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('admin_user_index');
        }

        $currentUser = $this->getUser();
        if ($currentUser instanceof User && $currentUser->getId() === $user->getId()) {
            $this->addFlash('error', 'Suppression impossible sur votre propre compte.');

            return $this->redirectToRoute('admin_user_index');
        }

        $userRepository->remove($user, true);
        $this->addFlash('success', 'Utilisateur supprimé.');

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/edit', name: 'admin_user_edit')]
    public function edit(Request $request, User $user, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): Response
    {
        $role = $user->getRoles()[0] ?? 'ROLE_USER';
        $form = $this->createForm(AdminUserType::class, $user, [
            'current_role' => $role,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $user->getEmail();
            $existing = $userRepository->findOneBy(['email' => $email]);
            if ($existing && $existing->getId() !== $user->getId()) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');

                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }

            $username = $user->getUsername();
            $existingUsername = $userRepository->findOneBy(['username' => $username]);
            if ($existingUsername && $existingUsername->getId() !== $user->getId()) {
                $this->addFlash('error', 'Ce pseudo est déjà utilisé.');

                return $this->redirectToRoute('admin_user_edit', ['id' => $user->getId()]);
            }

            $role = (string) $form->get('role')->getData();
            $user->setRoles([$role]);

            $plainPassword = (string) $form->get('plainPassword')->getData();
            if ($plainPassword !== '') {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $userRepository->save($user, true);
            $this->addFlash('success', 'Utilisateur mis à jour.');

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
