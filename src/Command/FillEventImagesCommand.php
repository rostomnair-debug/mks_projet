<?php

namespace App\Command;

use App\Repository\EventRepository;
use App\Service\PexelsImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fill-event-images',
    description: "Ajoute une image par defaut aux evenements sans image.",
)]
class FillEventImagesCommand extends Command
{
    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private PexelsImageService $pexelsImageService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $events = $this->eventRepository->findAll();
        $count = 0;
        $cleared = 0;

        foreach ($events as $event) {
            $path = $event->getImagePath();
            if ($path && str_starts_with($path, 'assets/events/')) {
                $event->setImagePath(null);
                $cleared++;
            }

            if ($event->getImagePath()) {
                continue;
            }

            $this->pexelsImageService->attachImageForEvent($event);
            if ($event->getImagePath()) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        $io->success("Images Pexels ajoutees: $count (placeholders supprimes: $cleared)");

        return Command::SUCCESS;
    }
}
