<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\AmpMetropoleImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-amp',
    description: 'Importe des lieux/événements depuis l API AMP Metropole.',
)]
class ImportAmpCommand extends Command
{
    public function __construct(
        private AmpMetropoleImporter $importer,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, "Email de l'organisateur", 'admin@mail.com')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Nombre de records', 20);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getOption('email');
        $limit = (int) $input->getOption('limit');

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error('Utilisateur introuvable : ' . $email);

            return Command::FAILURE;
        }

        $result = $this->importer->import($user, $limit);

        $io->success(sprintf(
            'Import OK. %d créé(s), %d maj, %d ignoré(s).',
            $result['created'],
            $result['updated'],
            $result['skipped']
        ));

        return Command::SUCCESS;
    }
}
