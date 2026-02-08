<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Repository\TaskCategoryRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/task')]
#[IsGranted('ROLE_USER')]
class TaskController extends AbstractController
{
    #[Route('/', name: 'app_task_index', methods: ['GET'])]
    public function index(
        Request $request, 
        TaskRepository $taskRepository, 
        TaskCategoryRepository $categoryRepository
    ): Response {
        $user = $this->getUser();
        
        // Get filter parameters
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $categoryId = $request->query->get('category');
        $sort = $request->query->get('sort', 'createdAt');
        $direction = $request->query->get('direction', 'DESC');
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // Build query
        $qb = $taskRepository->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user);
        
        // Apply filters
        if ($search) {
            $qb->andWhere('t.title LIKE :search OR t.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        if ($status) {
            $qb->andWhere('t.status = :status')
               ->setParameter('status', $status);
        }
        
        if ($priority) {
            $qb->andWhere('t.priority = :priority')
               ->setParameter('priority', $priority);
        }
        
        if ($categoryId) {
            $qb->andWhere('t.category = :category')
               ->setParameter('category', $categoryId);
        }
        
        // Apply sorting
        $allowedSorts = ['createdAt', 'priority', 'dueDate', 'title'];
        $allowedDirections = ['ASC', 'DESC'];
        
        if (in_array($sort, $allowedSorts) && in_array($direction, $allowedDirections)) {
            $qb->orderBy('t.' . $sort, $direction);
        } else {
            $qb->orderBy('t.createdAt', 'DESC');
        }
        
        // Get total count for pagination
        $totalTasks = (clone $qb)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalTasks / $limit);
        
        // Apply pagination
        $tasks = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
        
        // Get user's categories for filter dropdown
        $categories = $categoryRepository->findByUser($user);
        
        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
            'categories' => $categories,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalTasks' => $totalTasks,
        ]);
    }

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        TaskCategoryRepository $categoryRepository,
        NotificationService $notificationService
    ): Response {
        $task = new Task();
        $task->setUser($this->getUser());
        $task->setStatus('pending');
        $task->setPriority('medium');
        
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($task);
            $entityManager->flush();
            
            // Send notification to assigned user
            if ($task->getAssignedUser() && $task->getAssignedUser() !== $this->getUser()) {
                $notificationService->sendTaskAssignedNotification($task);
            }

            $this->addFlash('success', 'Задача успешно создана');

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        // Get user's categories for form
        $categories = $categoryRepository->findByUser($this->getUser());
        
        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form,
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Task $task, TaskCategoryRepository $categoryRepository): Response
    {
        $this->denyAccessUnlessGranted('TASK_VIEW', $task);
        
        $categories = $categoryRepository->findByUser($this->getUser());
        
        return $this->render('task/show.html.twig', [
            'task' => $task,
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Task $task, 
        EntityManagerInterface $entityManager,
        TaskCategoryRepository $categoryRepository,
        NotificationService $notificationService
    ): Response {
        $this->denyAccessUnlessGranted('TASK_EDIT', $task);
        
        $originalAssignedUser = $task->getAssignedUser();
        
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Send notification if assigned user changed
            if ($originalAssignedUser !== $task->getAssignedUser() && 
                $task->getAssignedUser() && 
                $task->getAssignedUser() !== $this->getUser()) {
                $notificationService->sendTaskAssignedNotification($task);
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Задача успешно обновлена');

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()], Response::HTTP_SEE_OTHER);
        }

        $categories = $categoryRepository->findByUser($this->getUser());
        
        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form,
            'categories' => $categories,
        ]);
    }

    #[Route('/{id}', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('TASK_DELETE', $task);
        
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
            $entityManager->remove($task);
            $entityManager->flush();
            
            $this->addFlash('success', 'Задача успешно удалена');
        }

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }
}