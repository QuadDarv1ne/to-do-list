<?php

namespace App\Controller;

use App\Entity\Task;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/task-template')]
class TaskTemplateController extends AbstractController
{
    #[Route('/', name: 'app_task_template_index')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_template_controller_index');
        }
        
        // Predefined task templates
        $templates = [
            [
                'name' => 'Ежедневная рутина',
                'description' => 'Стандартные ежедневные задачи',
                'tasks' => [
                    ['title' => 'Проверить почту', 'priority' => 'medium'],
                    ['title' => 'Обновить статус проектов', 'priority' => 'high'],
                    ['title' => 'План на день', 'priority' => 'medium']
                ]
            ],
            [
                'name' => 'Планирование недели',
                'description' => 'Задачи для планирования недели',
                'tasks' => [
                    ['title' => 'Обзор целей на неделю', 'priority' => 'high'],
                    ['title' => 'Планирование встреч', 'priority' => 'medium'],
                    ['title' => 'Подготовка отчетов', 'priority' => 'medium']
                ]
            ],
            [
                'name' => 'Проектный запуск',
                'description' => 'Шаблон для запуска новых проектов',
                'tasks' => [
                    ['title' => 'Определить цели проекта', 'priority' => 'high'],
                    ['title' => 'Собрать команду', 'priority' => 'high'],
                    ['title' => 'Создать план реализации', 'priority' => 'high'],
                    ['title' => 'Назначить сроки', 'priority' => 'medium']
                ]
            ],
            [
                'name' => 'Обучение и развитие',
                'description' => 'Задачи для профессионального роста',
                'tasks' => [
                    ['title' => 'Изучить новую технологию', 'priority' => 'medium'],
                    ['title' => 'Пройти онлайн-курс', 'priority' => 'medium'],
                    ['title' => 'Практические упражнения', 'priority' => 'low']
                ]
            ]
        ];

        try {
            return $this->render('task_template/index.html.twig', [
                'templates' => $templates
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_template_controller_index');
            }
        }
    }

    #[Route('/apply/{templateIndex}', name: 'app_task_template_apply')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function applyTemplate(
        int $templateIndex,
        Request $request,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {

        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_template_controller_apply');
        }

        // Predefined templates
        $templates = [
            [
                'name' => 'Ежедневная рутина',
                'tasks' => [
                    ['title' => 'Проверить почту', 'priority' => 'medium', 'description' => 'Проверить рабочую и личную почту на наличие новых сообщений'],
                    ['title' => 'Обновить статус проектов', 'priority' => 'high', 'description' => 'Обновить прогресс по текущим проектам в системе управления задачами'],
                    ['title' => 'План на день', 'priority' => 'medium', 'description' => 'Составить список приоритетных задач на текущий день']
                ]
            ],
            [
                'name' => 'Планирование недели',
                'tasks' => [
                    ['title' => 'Обзор целей на неделю', 'priority' => 'high', 'description' => 'Определить ключевые цели и результаты на предстоящую неделю'],
                    ['title' => 'Планирование встреч', 'priority' => 'medium', 'description' => 'Запланировать необходимые встречи и совещания'],
                    ['title' => 'Подготовка отчетов', 'priority' => 'medium', 'description' => 'Подготовить недельные отчеты по проектам и активностям']
                ]
            ],
            [
                'name' => 'Проектный запуск',
                'tasks' => [
                    ['title' => 'Определить цели проекта', 'priority' => 'high', 'description' => 'Четко сформулировать цели, ожидаемые результаты и критерии успеха проекта'],
                    ['title' => 'Собрать команду', 'priority' => 'high', 'description' => 'Определить состав команды и распределить роли и ответственность'],
                    ['title' => 'Создать план реализации', 'priority' => 'high', 'description' => 'Разработать детальный план выполнения проекта с этапами и сроками'],
                    ['title' => 'Назначить сроки', 'priority' => 'medium', 'description' => 'Установить реалистичные сроки выполнения каждого этапа проекта']
                ]
            ],
            [
                'name' => 'Обучение и развитие',
                'tasks' => [
                    ['title' => 'Изучить новую технологию', 'priority' => 'medium', 'description' => 'Погрузиться в изучение новой технологии или инструмента'],
                    ['title' => 'Пройти онлайн-курс', 'priority' => 'medium', 'description' => 'Зарегистрироваться и пройти соответствующий онлайн-курс'],
                    ['title' => 'Практические упражнения', 'priority' => 'low', 'description' => 'Выполнить практические задания для закрепления полученных знаний']
                ]
            ]
        ];

        if (!isset($templates[$templateIndex])) {
            $this->addFlash('error', 'Шаблон не найден');
            
            try {
                return $this->redirectToRoute('app_task_template_index');
            } finally {
                if ($performanceMonitor) {
                    $performanceMonitor->stopTimer('task_template_controller_apply');
                }
            }
        }

        $template = $templates[$templateIndex];
        $user = $this->getUser();

        // Create tasks from template
        $createdTasks = [];
        foreach ($template['tasks'] as $taskData) {
            $task = new Task();
            $task->setTitle($taskData['title']);
            $task->setDescription($taskData['description'] ?? '');
            $task->setPriority($taskData['priority']);
            $task->setStatus('pending');
            $task->setCreatedBy($user);
            $task->setAssignedUser($user);
            
            $entityManager->persist($task);
            $createdTasks[] = $task;
        }

        $entityManager->flush();

        $this->addFlash('success', sprintf('Создано %d задач из шаблона "%s"', count($createdTasks), $template['name']));

        try {
            return $this->redirectToRoute('app_task_index');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_template_controller_apply');
            }
        }
    }

    #[Route('/custom', name: 'app_task_template_custom')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function customTemplate(
        Request $request,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {

        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_template_controller_custom');
        }

        if ($request->isMethod('POST')) {
            $templateName = $request->request->get('template_name');
            $taskTitles = $request->request->all('task_titles');
            $taskPriorities = $request->request->all('task_priorities');
            $taskDescriptions = $request->request->all('task_descriptions');

            if (!$templateName || empty(array_filter($taskTitles))) {
                $this->addFlash('error', 'Пожалуйста, заполните название шаблона и хотя бы одну задачу');
                
                try {
                    return $this->render('task_template/custom.html.twig');
                } finally {
                    if ($performanceMonitor) {
                        $performanceMonitor->stopTimer('task_template_controller_custom');
                    }
                }
            }

            $user = $this->getUser();
            $createdTasks = [];

            // Create tasks from custom template
            foreach ($taskTitles as $index => $title) {
                if (trim($title)) {
                    $task = new Task();
                    $task->setTitle(trim($title));
                    $task->setDescription(trim($taskDescriptions[$index] ?? ''));
                    $task->setPriority($taskPriorities[$index] ?? 'medium');
                    $task->setStatus('pending');
                    $task->setCreatedBy($user);
                    $task->setAssignedUser($user);
                    
                    $entityManager->persist($task);
                    $createdTasks[] = $task;
                }
            }

            if (!empty($createdTasks)) {
                $entityManager->flush();
                $this->addFlash('success', sprintf('Создано %d задач из пользовательского шаблона "%s"', count($createdTasks), $templateName));
                
                try {
                    return $this->redirectToRoute('app_task_index');
                } finally {
                    if ($performanceMonitor) {
                        $performanceMonitor->stopTimer('task_template_controller_custom');
                    }
                }
            }
        }

        try {
            return $this->render('task_template/custom.html.twig');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_template_controller_custom');
            }
        }
    }
}