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
    name: 'app:create-test-users',
    description: 'Создает тестовых пользователей для системы',
)]
class CreateTestUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $testUsers = [
            [
                'email' => 'admin@example.com',
                'password' => 'admin123',
                'roles' => ['ROLE_ADMIN'],
                'name' => 'Администратор',
            ],
            [
                'email' => 'manager@example.com',
                'password' => 'manager123',
                'roles' => ['ROLE_MANAGER'],
                'name' => 'Менеджер',
            ],
            [
                'email' => 'user@example.com',
                'password' => 'user123',
                'roles' => ['ROLE_USER'],
                'name' => 'Пользователь',
            ],
            [
                'email' => 'analyst@example.com',
                'password' => 'analyst123',
                'roles' => ['ROLE_ANALYST'],
                'name' => 'Аналитик',
            ],
        ];

        foreach ($testUsers as $userData) {
            // Проверяем, существует ли пользователь
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $userData['email']]);

            if ($existingUser) {
                $io->warning(\sprintf('Пользователь %s уже существует', $userData['email']));

                continue;
            }

            $user = new User();
            $user->setEmail($userData['email']);
            $user->setUsername($userData['email']); // Используем email как username
            $user->setRoles($userData['roles']);
            $user->setIsActive(true);

            $hashedPassword = $this->passwordHasher->hashPassword(
                $user,
                $userData['password'],
            );
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $io->success(\sprintf('Создан пользователь: %s (%s)', $userData['name'], $userData['email']));
        }

        $this->entityManager->flush();

        $io->success('Все тестовые пользователи успешно созданы!');
        $io->note('Используйте учетные данные из docs/TEST_CREDENTIALS.md для входа');

        return Command::SUCCESS;
    }
}
