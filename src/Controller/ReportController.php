<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\Report;
use App\Form\ReportType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReportController extends AbstractController
{
    #[Route('/events/{slug}/report', name: 'event_report')]
    public function report(
        #[MapEntity(mapping: ['slug' => 'slug'])] Event $event,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $report = new Report();
        $report->setEvent($event);
        $report->setEventTitle($event->getTitle());
        $report->setEventSlug($event->getSlug());
        if ($this->getUser() instanceof \App\Entity\User) {
            $report->setUser($this->getUser());
        }

        $form = $this->createForm(ReportType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($report);
            $entityManager->flush();

            $this->addFlash('success', 'Merci, votre signalement a bien été envoyé.');

            return $this->redirectToRoute('event_show', ['slug' => $event->getSlug()]);
        }

        return $this->render('event/report.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }
}
