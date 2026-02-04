<?php

namespace App\Controller\Admin;

use App\Entity\Report;
use App\Repository\ReportRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reports')]
#[IsGranted('ROLE_ADMIN')]
class ReportAdminController extends AbstractController
{
    #[Route('/', name: 'admin_report_index')]
    public function index(Request $request, ReportRepository $reportRepository): Response
    {
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'reports'] + $request->query->all());
    }

    #[Route('/{id}', name: 'admin_report_show')]
    public function show(Report $report): Response
    {
        return $this->render('admin/report/show.html.twig', [
            'report' => $report,
        ]);
    }

    #[Route('/{id}/respond', name: 'admin_report_respond', methods: ['POST'])]
    public function respond(
        Report $report,
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if (!$this->isCsrfTokenValid('report_respond_' . $report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }

        $message = trim((string) $request->request->get('response'));
        if ($message === '') {
            $this->addFlash('error', 'Le message de réponse est obligatoire.');

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }

        $report->setAdminResponse($message);
        $report->setRespondedAt(new DateTimeImmutable());
        $report->setStatus(Report::STATUS_CLOSED);
        $entityManager->flush();

        $recipient = $report->getUser()?->getEmail();
        if ($recipient) {
            $email = (new TemplatedEmail())
                ->from('contact@mks.local')
                ->to($recipient)
                ->subject('Réponse à votre signalement')
                ->htmlTemplate('emails/report_response.html.twig')
                ->context([
                    'message' => $message,
                    'eventTitle' => $report->getEvent()?->getTitle() ?? $report->getEventTitle(),
                    'reportMessage' => $report->getDescription(),
                    'reportReasons' => $report->getReasons() ? implode(', ', $report->getReasons()) : null,
                    'logoUrl' => $request->getSchemeAndHttpHost() . '/assets/logos/mks-logo-main.svg',
                ]);
            $mailer->send($email);
            $this->addFlash('success', 'Réponse envoyée.');
        } else {
            $this->addFlash('warning', 'Réponse enregistrée, mais aucun email utilisateur associé.');
        }

        return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
    }

    #[Route('/{id}/block', name: 'admin_report_block', methods: ['POST'])]
    public function block(
        Report $report,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('report_block_' . $report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }

        $event = $report->getEvent();
        if (!$event) {
            $this->addFlash('error', "L'événement n'existe plus.");

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }
        $event->setIsPublished(false);
        $event->setUpdatedAt(new DateTimeImmutable());
        $report->setStatus(Report::STATUS_CLOSED);
        $report->setActionTaken('blocked');
        $report->setRespondedAt(new DateTimeImmutable());

        $entityManager->flush();

        $this->addFlash('success', "Événement bloqué (passé en brouillon).");

        return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
    }

    #[Route('/{id}/keep', name: 'admin_report_keep', methods: ['POST'])]
    public function keep(
        Report $report,
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if (!$this->isCsrfTokenValid('report_keep_' . $report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }

        if (!$report->getEvent()) {
            $this->addFlash('error', "L'événement n'existe plus.");

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }

        $report->setStatus(Report::STATUS_CLOSED);
        $report->setActionTaken('kept');
        $report->setRespondedAt(new DateTimeImmutable());
        $entityManager->flush();

        $recipient = $report->getUser()?->getEmail();
        if ($recipient) {
            $message = trim((string) $request->request->get('keep_message'));
            if ($message === '') {
                $message = "Après vérification, l'événement est maintenu.";
            }
            $email = (new TemplatedEmail())
                ->from('contact@mks.local')
                ->to($recipient)
                ->subject('Retour sur votre signalement')
                ->htmlTemplate('emails/report_response.html.twig')
                ->context([
                    'message' => $message,
                    'eventTitle' => $report->getEvent()?->getTitle() ?? $report->getEventTitle(),
                    'reportMessage' => $report->getDescription(),
                    'reportReasons' => $report->getReasons() ? implode(', ', $report->getReasons()) : null,
                    'logoUrl' => $request->getSchemeAndHttpHost() . '/assets/logos/mks-logo-main.svg',
                ]);
            $mailer->send($email);
        }

        $this->addFlash('success', 'Événement conservé.');

        return $this->redirectToRoute('admin_dashboard', ['tab' => 'reports']);
    }

    #[Route('/{id}/delete-event', name: 'admin_report_delete_event', methods: ['POST'])]
    public function deleteEvent(
        Report $report,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('report_delete_' . $report->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Action refusée.');

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }

        $event = $report->getEvent();
        if (!$event) {
            $this->addFlash('error', "L'événement n'existe plus.");

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }
        if ($event->getReservations()->count() > 0) {
            $this->addFlash('error', 'Suppression impossible : des réservations existent.');

            return $this->redirectToRoute('admin_report_show', ['id' => $report->getId()]);
        }

        $report->setEventTitle($event->getTitle());
        $report->setEventSlug($event->getSlug());
        $report->setEvent(null);
        $report->setStatus(Report::STATUS_CLOSED);
        $report->setActionTaken('deleted');
        $report->setRespondedAt(new DateTimeImmutable());

        $entityManager->remove($event);
        $entityManager->flush();

        $this->addFlash('success', 'Événement supprimé. Signalement conservé.');

        return $this->redirectToRoute('admin_dashboard', ['tab' => 'reports']);
    }
}
