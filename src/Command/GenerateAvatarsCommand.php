<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:generate-avatars',
    description: 'Genere des avatars par defaut pour les utilisateurs existants.',
)]
class GenerateAvatarsCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        #[Autowire('%app.profile_uploads_dir%')] private string $uploadsDir
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $users = $this->userRepository->findAll();
        $count = 0;

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            if ($user->getProfileImage()) {
                continue;
            }

            $user->setProfileImage($this->createDefaultAvatar($user));
            $count++;
        }

        if ($count > 0) {
            $this->userRepository->flush();
        }

        $io->success('Avatars generes: ' . $count);

        return Command::SUCCESS;
    }

    private function createDefaultAvatar(User $user): string
    {
        if (!is_dir($this->uploadsDir)) {
            @mkdir($this->uploadsDir, 0775, true);
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
        file_put_contents(rtrim($this->uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $filename, $svg);

        return 'uploads/avatars/' . $filename;
    }
}
