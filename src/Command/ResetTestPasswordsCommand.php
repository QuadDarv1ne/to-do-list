<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:reset-test-passwords',
    description: 'Сбрасывает пароли тестовых пользователей',
)]
class ResetTestPasswordsCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $testUsers = [
            'admin@example.com' => 'admin123',
            'manager@example.com' => 'manager123',
            'user@example.com' => 'user123',
            'analyst@example.com' => 'analyst123',
        ];

        foreach ($testUsers as $email => $password) {
            $user = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            if (!$user) {
                $io->warning(sprintf('Пользователь %s не найден', $email));
                continue;
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);
            $user->setIsActive(true);
            $user->unlockAccount();

            $this->entityManager->persist($user);
            $io->success(sprintf('Пароль сброшен для: %s', $email));
        }

        $this->entityManager->flush();

        $io->success('Все пароли успешно сброшены!');
        $io->table(
            ['Email', 'Пароль'],
            [
                ['admin@example.com', 'admin123'],
                ['manager@example.com', 'manager123'],
                ['user@example.com', 'user123'],
                ['analyst@example.com', 'analyst123'],
            ]
        );

        return Command::SUCCESS;
    }
}
