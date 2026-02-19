<?php

namespace App\Command;

use App\Entity\Task;
use App\Entity\TaskCategory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-test-data',
    description: 'Создает тестовые данные с правильными ролями и дедлайнами',
)]
class CreateTestDataCommand extends Command
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

        $io->title('Создание тестовых данных');

        // Create users with proper roles
        $users = $this->createUsers($io);

        // Create categories
        $categories = $this->createCategories($io, $users);

        // Create tasks with deadlines
        $this->createTasks($io, $users, $categories);

        $io->success('Тестовые данные успешно созданы!');
        $io->section('Учетные данные для входа:');
        $io->table(
            ['Роль', 'Email', 'Пароль', 'Права'],
            [
                ['Администратор', 'admin@example.com', 'admin123', 'Полный доступ ко всему'],
                ['Менеджер', 'manager@example.com', 'manager123', 'Управление задачами, отчеты, бюджет'],
                ['Аналитик', 'analyst@example.com', 'analyst123', 'Просмотр отчетов и аналитики'],
                ['Пользователь', 'user@example.com', 'user123', 'Работа со своими задачами'],
            ],
        );

        return Command::SUCCESS;
    }

    private function createUsers(SymfonyStyle $io): array
    {
        $io->section('Создание пользователей...');

        $usersData = [
            [
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => 'admin123',
                'roles' => ['ROLE_ADMIN'],
                'firstName' => 'Иван',
                'lastName' => 'Администратов',
                'position' => 'Системный администратор',
                'department' => 'IT',
            ],
            [
                'username' => 'manager',
                'email' => 'manager@example.com',
                'password' => 'manager123',
                'roles' => ['ROLE_MANAGER'],
                'firstName' => 'Петр',
                'lastName' => 'Менеджеров',
                'position' => 'Менеджер проектов',
                'department' => 'Управление',
            ],
            [
                'username' => 'analyst',
                'email' => 'analyst@example.com',
                'password' => 'analyst123',
                'roles' => ['ROLE_ANALYST'],
                'firstName' => 'Мария',
                'lastName' => 'Аналитикова',
                'position' => 'Бизнес-аналитик',
                'department' => 'Аналитика',
            ],
            [
                'username' => 'user',
                'email' => 'user@example.com',
                'password' => 'user123',
                'roles' => ['ROLE_USER'],
                'firstName' => 'Алексей',
                'lastName' => 'Пользователев',
                'position' => 'Разработчик',
                'department' => 'Разработка',
            ],
        ];

        $users = [];

        foreach ($usersData as $userData) {
            // Check if user exists
            $existingUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['email' => $userData['email']]);

            if ($existingUser) {
                $io->note("Пользователь {$userData['email']} уже существует, обновляем...");
                $user = $existingUser;
            } else {
                $user = new User();
                $io->text("Создаем пользователя: {$userData['email']}");
            }

            $user->setUsername($userData['username']);
            $user->setEmail($userData['email']);
            $user->setRoles($userData['roles']);
            $user->setFirstName($userData['firstName']);
            $user->setLastName($userData['lastName']);
            $user->setPosition($userData['position']);
            $user->setDepartment($userData['department']);
            $user->setIsActive(true);

            $hashedPassword = $this->passwordHasher->hashPassword($user, $userData['password']);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $users[$userData['username']] = $user;
        }

        $this->entityManager->flush();
        $io->success('Пользователи созданы!');

        return $users;
    }

    private function createCategories(SymfonyStyle $io, array $users): array
    {
        $io->section('Создание категорий...');

        $categoriesData = [
            ['name' => 'Разработка', 'description' => 'Задачи по разработке'],
            ['name' => 'Тестирование', 'description' => 'Задачи по тестированию'],
            ['name' => 'Документация', 'description' => 'Задачи по документации'],
            ['name' => 'Поддержка', 'description' => 'Задачи по поддержке'],
            ['name' => 'Маркетинг', 'description' => 'Задачи по маркетингу'],
        ];

        $categories = [];
        $admin = $users['admin'];

        foreach ($categoriesData as $categoryData) {
            $existingCategory = $this->entityManager->getRepository(TaskCategory::class)
                ->findOneBy(['name' => $categoryData['name'], 'user' => $admin]);

            if ($existingCategory) {
                $category = $existingCategory;
            } else {
                $category = new TaskCategory();
                $category->setName($categoryData['name']);
                $category->setDescription($categoryData['description']);
                $category->setUser($admin);
                $this->entityManager->persist($category);
            }

            $categories[] = $category;
        }

        $this->entityManager->flush();
        $io->success('Категории созданы!');

        return $categories;
    }

    private function createTasks(SymfonyStyle $io, array $users, array $categories): void
    {
        $io->section('Создание задач с дедлайнами...');

        $priorities = ['urgent', 'high', 'medium', 'low'];
        $statuses = ['pending', 'in_progress', 'completed'];

        $tasksData = [
            [
                'title' => 'Исправить критический баг в авторизации',
                'description' => 'Пользователи не могут войти в систему. Требуется срочное исправление.',
                'priority' => 'urgent',
                'status' => 'in_progress',
                'deadline' => '+1 day',
            ],
            [
                'title' => 'Разработать новый модуль отчетности',
                'description' => 'Создать модуль для генерации отчетов по продажам.',
                'priority' => 'high',
                'status' => 'in_progress',
                'deadline' => '+5 days',
            ],
            [
                'title' => 'Обновить документацию API',
                'description' => 'Добавить описание новых эндпоинтов.',
                'priority' => 'medium',
                'status' => 'pending',
                'deadline' => '+7 days',
            ],
            [
                'title' => 'Провести код-ревью PR #123',
                'description' => 'Проверить изменения в модуле аутентификации.',
                'priority' => 'high',
                'status' => 'pending',
                'deadline' => '+2 days',
            ],
            [
                'title' => 'Оптимизировать запросы к базе данных',
                'description' => 'Улучшить производительность медленных запросов.',
                'priority' => 'medium',
                'status' => 'pending',
                'deadline' => '+10 days',
            ],
            [
                'title' => 'Настроить CI/CD pipeline',
                'description' => 'Автоматизировать процесс деплоя.',
                'priority' => 'high',
                'status' => 'in_progress',
                'deadline' => '+3 days',
            ],
            [
                'title' => 'Провести тестирование нового функционала',
                'description' => 'Протестировать модуль календаря.',
                'priority' => 'high',
                'status' => 'pending',
                'deadline' => '+4 days',
            ],
            [
                'title' => 'Подготовить презентацию для клиента',
                'description' => 'Создать презентацию с демонстрацией новых возможностей.',
                'priority' => 'medium',
                'status' => 'pending',
                'deadline' => '+6 days',
            ],
            [
                'title' => 'Обновить зависимости проекта',
                'description' => 'Обновить все npm и composer пакеты.',
                'priority' => 'low',
                'status' => 'pending',
                'deadline' => '+14 days',
            ],
            [
                'title' => 'Провести встречу с командой',
                'description' => 'Обсудить планы на следующий спринт.',
                'priority' => 'medium',
                'status' => 'pending',
                'deadline' => 'today',
            ],
        ];

        $usersList = array_values($users);
        $taskCount = 0;

        foreach ($tasksData as $taskData) {
            $task = new Task();
            $task->setTitle($taskData['title']);
            $task->setDescription($taskData['description']);
            $task->setPriority($taskData['priority']);
            $task->setStatus($taskData['status']);

            // Set deadline
            $deadline = new \DateTime($taskData['deadline']);
            $task->setDeadline($deadline);

            // Assign to random user
            $creator = $usersList[array_rand($usersList)];
            $task->setUser($creator);

            // Assign to another user
            $assignee = $usersList[array_rand($usersList)];
            $task->setAssignedUser($assignee);

            // Assign random category
            $category = $categories[array_rand($categories)];
            $task->setCategory($category);

            $this->entityManager->persist($task);
            $taskCount++;
        }

        // Create more tasks for calendar
        for ($i = 0; $i < 20; $i++) {
            $task = new Task();
            $task->setTitle('Задача #' . ($taskCount + $i + 1));
            $task->setDescription('Описание задачи #' . ($taskCount + $i + 1));
            $task->setPriority($priorities[array_rand($priorities)]);
            $task->setStatus($statuses[array_rand($statuses)]);

            // Random deadline in next 30 days
            $daysOffset = rand(0, 30);
            $deadline = (new \DateTime())->modify("+{$daysOffset} days");
            $task->setDeadline($deadline);

            $creator = $usersList[array_rand($usersList)];
            $task->setUser($creator);

            $assignee = $usersList[array_rand($usersList)];
            $task->setAssignedUser($assignee);

            $category = $categories[array_rand($categories)];
            $task->setCategory($category);

            $this->entityManager->persist($task);
        }

        $this->entityManager->flush();
        $io->success('Создано ' . ($taskCount + 20) . ' задач с дедлайнами!');
    }
}
