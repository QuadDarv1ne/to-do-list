<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Создает администратора CRM системы',
)]
class CreateAdminCommand extends Command
{
    private EntityManagerInterface $entityManager;

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email администратора')
            ->addArgument('password', InputArgument::REQUIRED, 'Пароль администратора')
            ->addArgument('firstName', InputArgument::OPTIONAL, 'Имя администратора', 'Admin')
            ->addArgument('lastName', InputArgument::OPTIONAL, 'Фамилия администратора', 'Administrator');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $firstName = $input->getArgument('firstName');
        $lastName = $input->getArgument('lastName');

        // Проверяем, существует ли пользователь с таким email
        $existingUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        if ($existingUser) {
            $io->error(\sprintf('Пользователь с email "%s" уже существует!', $email));

            return Command::FAILURE;
        }

        // Создаем нового пользователя
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setUsername($email);
        $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);

        // Хешируем пароль
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Сохраняем пользователя
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('Администратор "%s" успешно создан!', $email));
        $io->table(
            ['Поле', 'Значение'],
            [
                ['Email', $user->getEmail()],
                ['ФИО', $user->getFullName()],
                ['Роли', implode(', ', $user->getRoles())],
                ['Создан', $user->getCreatedAt()->format('Y-m-d H:i:s')],
            ],
        );

        return Command::SUCCESS;
    }
}
