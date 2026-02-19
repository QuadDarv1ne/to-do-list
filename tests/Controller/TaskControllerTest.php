<?php

namespace App\Tests\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TaskControllerTest extends WebTestCase
{
    private $client;

    private $userRepository;

    private $testUser;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->userRepository = static::getContainer()->get(UserRepository::class);

        // Create a test user
        $this->testUser = $this->userRepository->findOneBy(['email' => 'test@example.com']);
        if (!$this->testUser) {
            $this->testUser = new User();
            $this->testUser->setEmail('test@example.com');
            $this->testUser->setUsername('testuser_unique_' . uniqid());
            $this->testUser->setPassword('$2y$13$example_hash');
            $this->testUser->setRoles(['ROLE_USER']);
            $this->testUser->setIsActive(true);

            $entityManager = static::getContainer()->get('doctrine')->getManager();
            $entityManager->persist($this->testUser);
            $entityManager->flush();
        }
    }

    public function testAccessTaskIndexWithoutLogin(): void
    {
        $this->client->request('GET', '/tasks');

        $this->assertResponseRedirects('/login', 302);
    }

    public function testAccessTaskIndexWithLogin(): void
    {
        // Simulate login
        $this->client->loginUser($this->testUser);

        // Symfony redirect adds trailing slash, so we expect redirect to /tasks/
        $this->client->request('GET', '/tasks');
        $this->assertResponseRedirects('/tasks/', 301);

        // Follow redirect and check success
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testCreateTask(): void
    {
        $this->client->loginUser($this->testUser);

        $crawler = $this->client->request('GET', '/tasks/new');

        $this->assertResponseIsSuccessful();

        // Submit the form
        $form = $crawler->selectButton('Создать')->form([
            'task[title]' => 'Тестовая задача',
            'task[description]' => 'Описание тестовой задачи',
            'task[priority]' => 'medium',
        ]);

        $this->client->submit($form);

        // Should redirect to tasks list
        $this->assertResponseRedirects('/tasks');
    }

    public function testViewTask(): void
    {
        $this->client->loginUser($this->testUser);

        // First create a task
        $task = new Task();
        $task->setTitle('Тестовая задача для просмотра');
        $task->setDescription('Описание');
        $task->setPriority('medium');
        $task->setUser($this->testUser);
        $task->setAssignedUser($this->testUser);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($task);
        $entityManager->flush();

        // Test viewing the task
        $this->client->request('GET', '/tasks/' . $task->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Тестовая задача для просмотра');
    }

    public function testEditTask(): void
    {
        $this->client->loginUser($this->testUser);

        // Create a task
        $task = new Task();
        $task->setTitle('Исходная задача');
        $task->setDescription('Исходное описание');
        $task->setPriority('medium');
        $task->setUser($this->testUser);
        $task->setAssignedUser($this->testUser);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($task);
        $entityManager->flush();

        // Test editing the task
        $crawler = $this->client->request('GET', '/tasks/' . $task->getId() . '/edit');

        $this->assertResponseIsSuccessful();

        // Submit the edit form
        $form = $crawler->selectButton('Сохранить')->form([
            'task[title]' => 'Обновленная задача',
            'task[description]' => 'Обновленное описание',
        ]);

        $this->client->submit($form);

        // Should redirect to task view
        $this->assertResponseRedirects('/tasks/' . $task->getId());

        // Check that the task was updated
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Обновленная задача');
    }

    public function testDeleteTask(): void
    {
        $this->client->loginUser($this->testUser);

        // Create a task
        $task = new Task();
        $task->setTitle('Задача для удаления');
        $task->setDescription('Описание');
        $task->setPriority('medium');
        $task->setUser($this->testUser);
        $task->setAssignedUser($this->testUser);

        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->persist($task);
        $entityManager->flush();

        // Test deleting the task
        $this->client->request('POST', '/tasks/' . $task->getId() . '/delete');

        // Should redirect to tasks list
        $this->assertResponseRedirects('/tasks');
    }

    public function testTaskSearch(): void
    {
        $this->client->loginUser($this->testUser);

        // Create test tasks
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        $task1 = new Task();
        $task1->setTitle('Важная задача');
        $task1->setDescription('Срочно нужно сделать');
        $task1->setPriority('high');
        $task1->setUser($this->testUser);
        $task1->setAssignedUser($this->testUser);

        $task2 = new Task();
        $task2->setTitle('Обычная задача');
        $task2->setDescription('Можно сделать позже');
        $task2->setPriority('medium');
        $task2->setUser($this->testUser);
        $task2->setAssignedUser($this->testUser);

        $entityManager->persist($task1);
        $entityManager->persist($task2);
        $entityManager->flush();

        // Test search
        $this->client->request('GET', '/tasks?search=важная');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.task-title', 'Важная задача');
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $entityManager = static::getContainer()->get('doctrine')->getManager();

        // Remove test tasks
        $tasks = $entityManager->getRepository(Task::class)->findBy(['user' => $this->testUser]);
        foreach ($tasks as $task) {
            $entityManager->remove($task);
        }

        // Remove test user if it was created during test
        $testUser = $this->userRepository->findOneBy(['email' => 'test@example.com']);
        if ($testUser && $testUser === $this->testUser) {
            // Re-attach the entity if it's detached
            if (!$entityManager->contains($testUser)) {
                $testUser = $entityManager->merge($testUser);
            }
            $entityManager->remove($testUser);
        }

        $entityManager->flush();

        parent::tearDown();
    }
}
