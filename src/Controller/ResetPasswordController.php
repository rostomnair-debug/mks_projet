<?php

namespace App\Controller;

use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    #[Route('/reset-password', name: 'app_forgot_password_request')]
    public function request(Request $request, ResetPasswordHelperInterface $resetPasswordHelper, MailerInterface $mailer, UserRepository $userRepository): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();

            return $this->processSendingPasswordResetEmail($email, $resetPasswordHelper, $mailer, $userRepository);
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/reset-password/check-email', name: 'app_check_email')]
    public function checkEmail(ResetPasswordHelperInterface $resetPasswordHelper): Response
    {
        $resetToken = $this->getTokenObjectFromSession();
        if (null === $resetToken) {
            $resetToken = $resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    #[Route('/reset-password/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, ResetPasswordHelperInterface $resetPasswordHelper, UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository, string $token = null): Response
    {
        if ($token) {
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();
        if (null === $token) {
            throw $this->createNotFoundException('Aucun token de réinitialisation trouvé.');
        }

        try {
            $user = $resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());

            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $resetPasswordHelper->removeResetRequest($token);

            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );
            $userRepository->save($user, true);

            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'Votre mot de passe a été mis à jour.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function processSendingPasswordResetEmail(string $email, ResetPasswordHelperInterface $resetPasswordHelper, MailerInterface $mailer, UserRepository $userRepository): RedirectResponse
    {
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        try {
            $resetToken = $resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $exception) {
            return $this->redirectToRoute('app_check_email');
        }

        $emailMessage = (new TemplatedEmail())
            ->from(new Address('no-reply@mks.local', 'MKS'))
            ->to($user->getEmail())
            ->subject('Réinitialiser votre mot de passe')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'logoUrl' => rtrim($this->generateUrl('home', [], UrlGeneratorInterface::ABSOLUTE_URL), '/') . '/assets/logos/mks-logo-main.svg',
            ]);

        $mailer->send($emailMessage);

        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_check_email');
    }
}
