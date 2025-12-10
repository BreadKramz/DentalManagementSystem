<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-user-status',
    description: 'Test user status functionality',
)]
class TestUserStatusCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email to test')
            ->addOption('action', null, InputOption::VALUE_REQUIRED, 'Action to perform (create-inactive, test-login, toggle-status)', 'create-inactive')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $action = $input->getOption('action');

        $user = $this->userRepository->findOneBy(['email' => $email]);

        switch ($action) {
            case 'create-inactive':
                if ($user) {
                    $io->error('User already exists!');
                    return Command::FAILURE;
                }

                $user = new User();
                $user->setEmail($email);
                $user->setPassword('$2y$13$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); // password
                $user->setRoles(['ROLE_USER']);
                $user->setStatus('inactive');

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $io->success(sprintf('Created inactive user: %s', $email));
                break;

            case 'test-login':
                if (!$user) {
                    $io->error('User does not exist!');
                    return Command::FAILURE;
                }

                if ($user->isActive()) {
                    $io->success(sprintf('User %s is ACTIVE and can login', $email));
                } else {
                    $io->warning(sprintf('User %s is INACTIVE and cannot login', $email));
                }
                break;

            case 'toggle-status':
                if (!$user) {
                    $io->error('User does not exist!');
                    return Command::FAILURE;
                }

                $currentStatus = $user->getStatus();
                $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';
                $user->setStatus($newStatus);
                $this->entityManager->flush();

                $io->success(sprintf('User %s status changed from %s to %s', $email, $currentStatus, $newStatus));
                break;

            default:
                $io->error('Invalid action. Use: create-inactive, test-login, or toggle-status');
                return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}