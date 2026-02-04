<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        #[Autowire('%app.profile_uploads_dir%')] string $uploadsDir
    ): Response
    {
        if ($request->isMethod('GET')) {
            return $this->redirectToRoute('app_login');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($userRepository->findOneBy(['email' => $user->getEmail()])) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->redirectToRoute('app_login');
            }
            if ($userRepository->findOneBy(['username' => $user->getUsername()])) {
                $this->addFlash('error', 'Ce pseudo est déjà utilisé.');
                return $this->redirectToRoute('app_login');
            }

            $user->setRoles(['ROLE_USER']);
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            if ($user->getProfileImage() === null) {
                $user->setProfileImage($this->createDefaultAvatar($user, $uploadsDir));
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@mks.local', 'MKS'))
                ->to($user->getEmail())
                ->subject('Confirme ton compte MKS')
                ->htmlTemplate('registration/confirmation_email.html.twig')
                ->context([
                    'logoUrl' => $request->getSchemeAndHttpHost() . '/assets/logos/mks-logo-main.svg',
                ]);

            $target = (string) $request->request->get('target', '');
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user, $email, [
                'target' => $target,
                'logoUrl' => $request->getSchemeAndHttpHost() . '/assets/logos/mks-logo-main.svg',
            ]);

            $this->addFlash('success', 'Un email de confirmation a été envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, UserRepository $userRepository, Security $security): Response
    {
        $id = $request->get('id');
        if (null === $id) {
            return $this->redirectToRoute('home');
        }

        $user = $userRepository->find($id);
        if (null === $user) {
            return $this->redirectToRoute('home');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());

            return $this->redirectToRoute('app_register');
        }

        $this->addFlash('success', 'Votre email est vérifié. Vous êtes connecté.');

        $security->login($user, 'form_login', 'main');

        $target = (string) $request->get('target', '');
        if ($this->isSafeTarget($target)) {
            return $this->redirect($target);
        }

        return $this->redirectToRoute('account_profile');
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

    private function createDefaultAvatar(User $user, string $uploadsDir): string
    {
        if (!is_dir($uploadsDir)) {
            @mkdir($uploadsDir, 0775, true);
        }

        $name = trim(($user->getFirstName() ?? '') . ' ' . ($user->getLastName() ?? ''));
        if ($name === '') {
            $name = $user->getUsername() ?: $user->getEmail();
        }

        $initials = strtoupper(substr($name, 0, 1));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256">'
            . '<rect width="256" height="256" fill="#1D7EA5"/>'
            . '<text x="50%" y="54%" text-anchor="middle" dominant-baseline="middle" font-family="Inter, Arial, sans-serif" font-size="120" fill="#F7F1E6">'
            . htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
            . '</text></svg>';

        $filename = 'avatar-' . uniqid() . '.svg';
        file_put_contents(rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $filename, $svg);

        return 'uploads/avatars/' . $filename;
    }
}
