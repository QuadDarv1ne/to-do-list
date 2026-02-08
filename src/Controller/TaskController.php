<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\Comment;
use App\Form\TaskType;
use App\Form\CommentType;
use App\Repository\TaskRepository;
use App\Repository\CommentRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Entity\TaskTimeTracking;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

#[Route('/tasks')]
class TaskController extends AbstractController
{
    public function __construct(
        private NotificationService $notificationService
    ) {
    }

    #[Route('/', name: 'app_task_index', methods: ['GET'])]
    public function index(TaskRepository $taskRepository, Request $request): Response
    {
        $user = $this->getUser();
        
        // Get search query from request
        $searchQuery = $request->query->get('search', '');
        
        if ($user->hasRole('ROLE_ADMIN')) {
            if ($searchQuery) {
                $tasks = $taskRepository->findBySearchQuery($searchQuery);
            } else {
                $tasks = $taskRepository->findAll();
            }
        } else {
            if ($searchQuery) {
                $tasks = $taskRepository->findBySearchQueryAndUser($searchQuery, $user);
            } else {
                $tasks = $taskRepository->findByAssignedToOrCreatedBy($user);
            }
        }

        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
            'searchQuery' => $searchQuery,
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
                    $task->getAssignedUser() ? $task->getAssignedUser()->getFullName() : '',
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
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setCreatedBy($this->getUser());
            
            $entityManager->persist($task);
            $entityManager->flush();

            // Notify the assigned user if different from creator
            if ($task->getAssignedUser() && $task->getAssignedUser() !== $this->getUser()) {
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
    public function show(Task $task, CommentRepository $commentRepository): Response
    {
        // Check if user can access this task
        if (!$this->isGranted('TASK_VIEW', $task)) {
            throw $this->createAccessDeniedException('У вас нет прав для просмотра этой задачи.');
        }
        
        // Create comment form
        $comment = new Comment();
        $comment->setTask($task);
        $comment->setAuthor($this->getUser());
        $commentForm = $this->createForm(CommentType::class, $comment);
        
        $comments = $commentRepository->findByTask($task);
        
        return $this->render('task/show.html.twig', [
            'task' => $task,
            'comments' => $comments,
            'comment_form' => $commentForm,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        // Check if user can edit this task
        if (!$this->isGranted('TASK_EDIT', $task)) {
            throw $this->createAccessDeniedException('У вас нет прав для редактирования этой задачи.');
        }
        
        $originalAssignedTo = $task->getAssignedUser(); // Store original assignee
        
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Notify the newly assigned user if different from original
            if ($task->getAssignedUser() && $task->getAssignedUser() !== $originalAssignedTo && $task->getAssignedUser() !== $this->getUser()) {
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
        
        $task->setIsDone(!$task->isDone());
        $entityManager->flush();

        return $this->redirectToRoute('app_task_index');
    }

    #[Route('/{id}/comments', name: 'app_task_comments', methods: ['GET'])]
    public function comments(Task $task, CommentRepository $commentRepository): Response
    {
        // Check if user can view this task
        if (!$this->isGranted('TASK_VIEW', $task)) {
            throw $this->createAccessDeniedException('У вас нет прав для просмотра этой задачи.');
        }
        
        $comments = $commentRepository->findByTask($task);
        
        return $this->render('task/comments.html.twig', [
            'task' => $task,
            'comments' => $comments,
        ]);
    }
    
    #[Route('/{id}/time-tracking', name: 'app_task_time_tracking', methods: ['GET', 'POST'])]
    public function timeTracking(Task $task, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('view', $task);
        
        $timeTracking = new TaskTimeTracking();
        $timeTracking->setTask($task);
        $timeTracking->setUser($this->getUser());
        $timeTracking->setDateLogged(new \DateTimeImmutable());
        
        $form = $this->createFormBuilder($timeTracking)
            ->add('timeSpent', TimeType::class, [
                'label' => 'Затраченное время',
                'widget' => 'single_text',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Описание работы',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Сохранить время',
                'attr' => ['class' => 'btn btn-primary'],
            ])
            ->getForm();
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($timeTracking);
            $entityManager->flush();
            
            $this->addFlash('success', 'Время успешно записано!');
            
            return $this->redirectToRoute('app_task_time_tracking', ['id' => $task->getId()]);
        }
        
        $timeTrackings = $entityManager->getRepository(TaskTimeTracking::class)->findByTask($task);
        $totalTime = $entityManager->getRepository(TaskTimeTracking::class)->getTotalTimeForTask($task);
        
        return $this->render('task/time_tracking.html.twig', [
            'task' => $task,
            'form' => $form->createView(),
            'time_trackings' => $timeTrackings,
            'total_time' => $totalTime,
        ]);
    }
}