<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Entity\TaskTemplateItem;
use App\Repository\TaskTemplateRepository;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/task-template')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TaskTemplateController extends AbstractController
{
    /** Predefined system templates (not stored in DB) */
    private function getPredefinedTemplates(): array
    {
        return [
            [
                'name' => 'Ежедневная рутина',
                'description' => 'Стандартные ежедневные задачи',
                'tasks' => [
                    ['title' => 'Проверить почту', 'priority' => 'medium', 'description' => 'Проверить рабочую и личную почту'],
                    ['title' => 'Обновить статус проектов', 'priority' => 'high', 'description' => 'Обновить прогресс по текущим проектам'],
                    ['title' => 'План на день', 'priority' => 'medium', 'description' => 'Составить список приоритетных задач'],
                ],
            ],
            [
                'name' => 'Планирование недели',
                'description' => 'Задачи для планирования недели',
                'tasks' => [
                    ['title' => 'Обзор целей на неделю', 'priority' => 'high', 'description' => 'Определить ключевые цели на неделю'],
                    ['title' => 'Планирование встреч', 'priority' => 'medium', 'description' => 'Запланировать необходимые встречи'],
                    ['title' => 'Подготовка отчетов', 'priority' => 'medium', 'description' => 'Подготовить недельные отчеты'],
                ],
            ],
            [
                'name' => 'Проектный запуск',
                'description' => 'Шаблон для запуска новых проектов',
                'tasks' => [
                    ['title' => 'Определить цели проекта', 'priority' => 'high', 'description' => 'Сформулировать цели и критерии успеха'],
                    ['title' => 'Собрать команду', 'priority' => 'high', 'description' => 'Определить состав команды и роли'],
                    ['title' => 'Создать план реализации', 'priority' => 'high', 'description' => 'Разработать детальный план с этапами'],
                    ['title' => 'Назначить сроки', 'priority' => 'medium', 'description' => 'Установить реалистичные сроки'],
                ],
            ],
            [
                'name' => 'Обучение и развитие',
                'description' => 'Задачи для профессионального роста',
                'tasks' => [
                    ['title' => 'Изучить новую технологию', 'priority' => 'medium', 'description' => 'Изучить новый инструмент или технологию'],
                    ['title' => 'Пройти онлайн-курс', 'priority' => 'medium', 'description' => 'Зарегистрироваться и пройти курс'],
                    ['title' => 'Практические упражнения', 'priority' => 'low', 'description' => 'Выполнить практические задания'],
                ],
            ],
        ];
    }

    #[Route('/', name: 'app_task_template_index')]
    public function index(TaskTemplateRepository $templateRepository): Response
    {
        $savedTemplates = $templateRepository->findByUser($this->getUser());

        return $this->render('task_template/index.html.twig', [
            'templates'      => $this->getPredefinedTemplates(),
            'savedTemplates' => $savedTemplates,
        ]);
    }

    /** Apply a predefined (hardcoded) template */
    #[Route('/apply/{templateIndex}', name: 'app_task_template_apply', methods: ['POST', 'GET'])]
    public function applyPredefined(
        int $templateIndex,
        EntityManagerInterface $entityManager,
    ): Response {
        $templates = $this->getPredefinedTemplates();

        if (!isset($templates[$templateIndex])) {
            $this->addFlash('error', 'Шаблон не найден');
            return $this->redirectToRoute('app_task_template_index');
        }

        $template = $templates[$templateIndex];
        $user = $this->getUser();
        $created = 0;

        foreach ($template['tasks'] as $taskData) {
            $task = new Task();
            $task->setTitle($taskData['title']);
            $task->setDescription($taskData['description'] ?? '');
            $task->setPriority($taskData['priority']);
            $task->setStatus('pending');
            $task->setCreatedBy($user);
            $task->setAssignedUser($user);
            $entityManager->persist($task);
            ++$created;
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('Создано %d задач из шаблона «%s»', $created, $template['name']));

        return $this->redirectToRoute('app_task_index');
    }

    /** Apply a saved (DB) template */
    #[Route('/saved/{id}/apply', name: 'app_task_template_saved_apply', methods: ['POST', 'GET'])]
    public function applySaved(
        TaskTemplate $template,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($template->getUser() !== $this->getUser() && !$template->isPublic()) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        $created = 0;

        foreach ($template->getItems() as $item) {
            $task = new Task();
            $task->setTitle($item->getTitle());
            $task->setDescription($item->getDescription() ?? '');
            $task->setPriority($item->getPriority());
            $task->setStatus('pending');
            $task->setCreatedBy($user);
            $task->setAssignedUser($user);
            $entityManager->persist($task);
            ++$created;
        }

        $entityManager->flush();
        $this->addFlash('success', sprintf('Создано %d задач из шаблона «%s»', $created, $template->getName()));

        return $this->redirectToRoute('app_task_index');
    }

    /** Delete a saved template */
    #[Route('/saved/{id}/delete', name: 'app_task_template_saved_delete', methods: ['POST'])]
    public function deleteSaved(
        TaskTemplate $template,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($template->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-template-' . $template->getId(), $request->request->get('_token'))) {
            $entityManager->remove($template);
            $entityManager->flush();
            $this->addFlash('success', sprintf('Шаблон «%s» удалён', $template->getName()));
        }

        return $this->redirectToRoute('app_task_template_index');
    }

    /** Save a task as a single-item template */
    #[Route('/from-task/{id}', name: 'app_task_template_from_task', methods: ['POST'])]
    public function fromTask(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($task->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $templateName = trim($request->request->get('template_name', $task->getTitle()));
        if (!$templateName) {
            $templateName = $task->getTitle();
        }

        $template = new TaskTemplate();
        $template->setName($templateName);
        $template->setDescription($task->getDescription());
        $template->setUser($this->getUser());

        $item = new TaskTemplateItem();
        $item->setTitle($task->getTitle());
        $item->setDescription($task->getDescription());
        $item->setPriority($task->getPriority());
        $item->setSortOrder(0);
        $template->addItem($item);

        $entityManager->persist($template);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Задача сохранена как шаблон «%s»', $template->getName()));

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }

    /** Save a custom template to DB */
    #[Route('/custom', name: 'app_task_template_custom')]
    public function customTemplate(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($request->isMethod('POST')) {
            $templateName = trim($request->request->get('template_name', ''));
            $templateDesc = trim($request->request->get('template_description', ''));
            $taskTitles       = $request->request->all('task_titles');
            $taskPriorities   = $request->request->all('task_priorities');
            $taskDescriptions = $request->request->all('task_descriptions');

            if (!$templateName || empty(array_filter($taskTitles))) {
                $this->addFlash('error', 'Пожалуйста, заполните название шаблона и хотя бы одну задачу');
                return $this->render('task_template/custom.html.twig');
            }

            $template = new TaskTemplate();
            $template->setName($templateName);
            $template->setDescription($templateDesc ?: null);
            $template->setUser($this->getUser());

            $sortOrder = 0;
            foreach ($taskTitles as $i => $title) {
                $title = trim($title);
                if (!$title) {
                    continue;
                }
                $item = new TaskTemplateItem();
                $item->setTitle($title);
                $item->setDescription(trim($taskDescriptions[$i] ?? '') ?: null);
                $item->setPriority($taskPriorities[$i] ?? 'medium');
                $item->setSortOrder($sortOrder++);
                $template->addItem($item);
            }

            $entityManager->persist($template);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Шаблон «%s» сохранён (%d задач)', $template->getName(), $template->getItems()->count()));

            return $this->redirectToRoute('app_task_template_index');
        }

        return $this->render('task_template/custom.html.twig');
    }
}
