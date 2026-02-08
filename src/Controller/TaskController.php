<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Route('/tasks')]
class TaskController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    #[Route('/', name: 'app_task_index', methods: ['GET'])]
    public function index(TaskRepository $taskRepository): Response
    {
        $user = $this->getUser();
        
        if ($user->hasRole('ROLE_ADMIN')) {
            $tasks = $taskRepository->findAll();
        } else {
            $tasks = $taskRepository->findByAssignedToOrCreatedBy($user);
        }

        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
        ]);
    }

    #[Route('/export', name: 'app_task_export', methods: ['GET'])]
    public function export(TaskRepository $taskRepository): StreamedResponse
    {
        $user = $this->getUser();
        
        if ($user->hasRole('ROLE_ADMIN')) {
            $tasks = $taskRepository->findAll();
        } else {
            $tasks = $taskRepository->findByAssignedToOrCreatedBy($user);
        }

        $response = new StreamedResponse(function () use ($tasks) {
            $handle = fopen('php://output', 'w+');
            
            // Add BOM (byte order mark) to make sure Excel reads UTF-8 correctly
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write CSV headers
            fputcsv($handle, [
                'ID',
                'Заголовок',
                'Описание',
                'Статус',
                'Дата создания',
                'Дата обновления',
                'Срок выполнения',
                'Приоритет',
                'Назначен пользователю',
                'Создан пользователем'
            ], ';'); // Using semicolon as separator for better Excel compatibility
            
            // Write task data
            foreach ($tasks as $task) {
                fputcsv($handle, [
                    $task->getId(),
                    $task->getTitle(),
                    strip_tags($task->getDescription()), // Remove HTML tags for clean export
                    $task->getStatus(),
                    $task->getCreatedAt()->format('d.m.Y H:i'),
                    $task->getUpdatedAt()->format('d.m.Y H:i'),
                    $task->getDeadline() ? $task->getDeadline()->format('d.m.Y') : '',
                    $task->getPriority(),
                    $task->getAssignedTo() ? $task->getAssignedTo()->getFullName() : '',
                    $task->getCreatedBy() ? $task->getCreatedBy()->getFullName() : ''
                ], ';');
            }
            
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/csv');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'tasks_export_' . date('Y-m-d_H-i-s') . '.csv'
        ));

        return $response;
    }

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();
        $task->setCreatedBy($this->getUser());
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setCreatedBy($this->getUser());
            
            $entityManager->persist($task);
            $entityManager->flush();

            // Notify the assigned user if different from creator
            if ($task->getAssignedTo() && $task->getAssignedTo() !== $this->getUser()) {
                $this->notificationService->notifyTaskAssignment($task, $this->getUser());
            }

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/form.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        // Check if user can access this task
        if (!$this->isGranted('TASK_VIEW', $task)) {
            throw $this->createAccessDeniedException('У вас нет прав для просмотра этой задачи.');
        }
        
        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        // Check if user can edit this task
        if (!$this->isGranted('TASK_EDIT', $task)) {
            throw $this->createAccessDeniedException('У вас нет прав для редактирования этой задачи.');
        }
        
        $originalAssignedTo = $task->getAssignedTo(); // Store original assignee
        
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Notify the newly assigned user if different from original
            if ($task->getAssignedTo() && $task->getAssignedTo() !== $originalAssignedTo && $task->getAssignedTo() !== $this->getUser()) {
                $this->notificationService->notifyTaskReassignment($task, $this->getUser());
            }

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/form.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        // Check if user can delete this task
        if (!$this->isGranted('TASK_DELETE', $task)) {
            throw $this->createAccessDeniedException('У вас нет прав для удаления этой задачи.');
        }
        
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
            $entityManager->remove($task);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle', name: 'app_task_toggle', methods: ['POST'])]
    public function toggle(Task $task, EntityManagerInterface $entityManager): Response
    {
        // Check if user can toggle this task
        if (!$this->isGranted('TASK_TOGGLE', $task)) {
            throw $this->createAccessDeniedException('У вас нет прав для изменения статуса этой задачи.');
        }
        
        $task->setStatus($task->isDone() ? 'in_progress' : 'done');
        $entityManager->flush();

        return $this->redirectToRoute('app_task_index');
    }
}