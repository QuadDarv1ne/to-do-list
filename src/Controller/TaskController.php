<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\User;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Repository\TaskCategoryRepository;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\PerformanceMonitorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/task')]
#[IsGranted('ROLE_USER')]
class TaskController extends AbstractController
{
    #[Route('/', name: 'app_task_index', methods: ['GET'])]
    public function index(
        Request $request, 
        TaskRepository $taskRepository, 
        TaskCategoryRepository $categoryRepository,
        TagRepository $tagRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_index');
        }
        
        $user = $this->getUser();
        
        // Get filter parameters
        $search = $request->query->get('search');
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $categoryId = $request->query->get('category');
        $tagId = $request->query->get('tag');
        $hideCompleted = $request->query->get('hide_completed', false);
        $sort = $request->query->get('sort', 'createdAt');
        $direction = $request->query->get('direction', 'DESC');
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        // Build query with eager loading to prevent N+1
        $qb = $taskRepository->createQueryBuilder('t')
            ->leftJoin('t.assignedUser', 'au')->addSelect('au')
            ->leftJoin('t.category', 'c')->addSelect('c')
            ->leftJoin('t.tags', 'tags')->addSelect('tags')
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
        
        if ($tagId) {
            $qb->join('t.tags', 'jt')
               ->andWhere('jt.id = :tagId')
               ->setParameter('tagId', $tagId);
        }
        
        if ($hideCompleted) {
            $qb->andWhere('t.status != :completedStatus')
               ->setParameter('completedStatus', 'completed');
        }
        
        // Apply sorting
        $allowedSorts = ['createdAt', 'priority', 'dueDate', 'title', 'tag_count'];
        $allowedDirections = ['ASC', 'DESC'];
        
        if ($sort === 'tag_count') {
            // Special handling for tag count sorting
            $qb->select('t, au, c, tags, COUNT(tags.id) as HIDDEN tag_count')
               ->groupBy('t.id, au.id, c.id, tags.id')
               ->orderBy('tag_count', $direction)
               ->addOrderBy('t.createdAt', 'DESC');
        } elseif (in_array($sort, $allowedSorts) && in_array($direction, $allowedDirections)) {
            $qb->orderBy('t.' . $sort, $direction);
        } else {
            $qb->orderBy('t.createdAt', 'DESC');
        }
        
        // Handle pagination differently when sorting by tag count
        if ($sort === 'tag_count') {
            // For tag count sorting, get all matching tasks and handle pagination in memory
            $allTasks = $qb->getQuery()->getResult();
            $totalTasks = count($allTasks);
            $totalPages = ceil($totalTasks / $limit);
            
            // Apply pagination manually
            $tasks = array_slice($allTasks, $offset, $limit);
        } else {
            // Get total count for pagination
            $totalTasks = (clone $qb)->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();
            $totalPages = ceil($totalTasks / $limit);
            
            // Apply pagination
            $tasks = $qb
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }
        
        // Get user's categories for filter dropdown
        $categories = $categoryRepository->findByUser($user);
        
        // Get all tags for filter dropdown
        $tags = $tagRepository->findByUser($user);
        
        try {
            return $this->render('task/index.html.twig', [
                'tasks' => $tasks,
                'categories' => $categories,
                'tags' => $tags,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalTasks' => $totalTasks,
                'searchQuery' => $search,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_index');
            }
        }
    }

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request, 
        EntityManagerInterface $entityManager,
        TaskCategoryRepository $categoryRepository,
        NotificationService $notificationService,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_new');
        }
        
        $task = new Task();
        $task->setUser($this->getUser());
        $task->setStatus('pending');
        $task->setPriority('medium');
        
        $form = $this->createForm(TaskType::class, $task, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($task);
            $entityManager->flush();
            
            // Send notification to assigned user
            if ($task->getAssignedUser() && $task->getAssignedUser() !== $this->getUser()) {
                $notificationService->sendTaskAssignmentNotification($task->getAssignedUser(), $this->getUser(), $task->getId(), $task->getName());
            }

            $this->addFlash('success', 'Задача успешно создана');

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        // Get user's categories for form
        $categories = $categoryRepository->findByUser($this->getUser());
        
        try {
            return $this->render('task/new.html.twig', [
                'task' => $task,
                'form' => $form,
                'categories' => $categories,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_new');
            }
        }
    }

    #[Route('/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(int $id, TaskRepository $taskRepository, TaskCategoryRepository $categoryRepository, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_show');
        }
        
        $task = $taskRepository->findTaskWithRelations($id);
        
        if (!$task) {
            throw $this->createNotFoundException('Task not found');
        }
        
        $this->denyAccessUnlessGranted('TASK_VIEW', $task);
        
        $categories = $categoryRepository->findByUser($this->getUser());
        
        try {
            return $this->render('task/show.html.twig', [
                'task' => $task,
                'categories' => $categories,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_show');
            }
        }
    }

    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Task $task, 
        EntityManagerInterface $entityManager,
        TaskCategoryRepository $categoryRepository,
        NotificationService $notificationService,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_edit');
        }
        
        $this->denyAccessUnlessGranted('TASK_EDIT', $task);
        
        $originalAssignedUser = $task->getAssignedUser();
        $originalStatus = $task->getStatus();
        $originalPriority = $task->getPriority();
        $originalDueDate = $task->getDueDate();
        
        $form = $this->createForm(TaskType::class, $task, ['user' => $this->getUser()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Send notification if assigned user changed
            if ($originalAssignedUser !== $task->getAssignedUser() && 
                $task->getAssignedUser() && 
                $task->getAssignedUser() !== $this->getUser()) {
                $notificationService->sendTaskAssignmentNotification($task->getAssignedUser(), $this->getUser(), $task->getId(), $task->getName());
            }
            
            // Send notification if status changed
            if ($originalStatus !== $task->getStatus()) {
                $notificationService->sendTaskCompletionNotification($task->getUser(), $this->getUser(), $task->getId(), $task->getName());
            }
            
            // Send notification if priority changed
            if ($originalPriority !== $task->getPriority()) {
                // Priority change notifications not implemented in NotificationService
            }
            
            // Send notification if due date changed
            if (($originalDueDate?->format('Y-m-d') ?? null) !== ($task->getDueDate()?->format('Y-m-d') ?? null)) {
                // Due date change notifications not implemented in NotificationService
            }
            
            $entityManager->flush();

            $this->addFlash('success', 'Задача успешно обновлена');

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()], Response::HTTP_SEE_OTHER);
        }

        $categories = $categoryRepository->findByUser($this->getUser());
        
        try {
            return $this->render('task/edit.html.twig', [
                'task' => $task,
                'form' => $form,
                'categories' => $categories,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_edit');
            }
        }
    }
        
    #[Route('/{id}/clone', name: 'app_task_clone', methods: ['POST'])]
    public function cloneTask(Task $task, EntityManagerInterface $entityManager, NotificationService $notificationService, ?PerformanceMonitorService $performanceMonitor = null): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_clone');
        }
        
        $this->denyAccessUnlessGranted('TASK_VIEW', $task);
            
        // Create a new task with the same properties
        $clonedTask = new Task();
        $clonedTask->setTitle($task->getTitle() . ' (копия)');
        $clonedTask->setDescription($task->getDescription());
        $clonedTask->setUser($this->getUser()); // Cloner becomes the owner
        $clonedTask->setStatus('pending'); // New tasks start as pending
        $clonedTask->setPriority($task->getPriority());
        $clonedTask->setDueDate($task->getDueDate());
        $clonedTask->setCategory($task->getCategory());
        $clonedTask->setAssignedUser($task->getAssignedUser()); // Keep the same assigned user
        $clonedTask->setCreatedAt(new \DateTimeImmutable());
            
        // Clone tags
        foreach ($task->getTags() as $tag) {
            $clonedTask->addTag($tag);
        }
            
        $entityManager->persist($clonedTask);
        $entityManager->flush();
            
        $this->addFlash('success', 'Задача успешно скопирована');
            
        try {
            return $this->redirectToRoute('app_task_show', ['id' => $clonedTask->getId()]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_clone');
            }
        }
    }
        
    #[Route('/api/categories', name: 'app_api_categories', methods: ['GET'])]
    public function apiCategories(TaskCategoryRepository $categoryRepository, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_api_categories');
        }
        $user = $this->getUser();
        $categories = $categoryRepository->findByUser($user);
            
        $data = [];
        foreach ($categories as $category) {
            $data[] = [
                'id' => $category->getId(),
                'name' => $category->getName()
            ];
        }
            
        try {
            return $this->json($data);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_api_categories');
            }
        }
    }
        
    #[Route('/api/tags', name: 'app_api_tags', methods: ['GET'])]
    public function apiTags(TagRepository $tagRepository, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_api_tags');
        }
        $user = $this->getUser();
        $tags = $tagRepository->findByUser($user);
            
        $data = [];
        foreach ($tags as $tag) {
            $data[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName()
            ];
        }
            
        try {
            return $this->json($data);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_api_tags');
            }
        }
    }
        
    #[Route('/api/users', name: 'app_api_users', methods: ['GET'])]
    public function apiUsers(UserRepository $userRepository, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_api_users');
        }
        $user = $this->getUser();
        // Get all users except the current user (for assignment purposes)
        $users = $userRepository->findAll();
            
        $data = [];
        foreach ($users as $u) {
            // Only include active users
            if ($u->isActive()) {
                $data[] = [
                    'id' => $u->getId(),
                    'fullName' => $u->getFullName()
                ];
            }
        }
            
        try {
            return $this->json($data);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_api_users');
            }
        }
    }
        
    #[Route('/{id}', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $entityManager, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_delete');
        }
        $this->denyAccessUnlessGranted('TASK_DELETE', $task);
        
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
            $entityManager->remove($task);
            $entityManager->flush();
            
            $this->addFlash('success', 'Задача успешно удалена');
        }

        try {
            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_delete');
            }
        }
    }
    
    #[Route('/search', name: 'app_task_search', methods: ['GET'])]
    public function search(
        Request $request, 
        TaskRepository $taskRepository, 
        TaskCategoryRepository $categoryRepository,
        TagRepository $tagRepository,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_search');
        }
        $user = $this->getUser();
        
        // Get search parameters with validation
        $search = $request->query->get('q', '');
        $search = is_string($search) ? trim($search) : '';
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $categoryId = $request->query->get('category');
        $tagId = $request->query->get('tag');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $createdAfter = $request->query->get('created_after');
        $createdBefore = $request->query->get('created_before');
        $assignedToMe = $request->query->get('assigned_to_me');
        $createdByMe = $request->query->get('created_by_me');
        $overdue = $request->query->get('overdue');
        $sortBy = $request->query->get('sort_by');
        $sortDirection = $request->query->get('sort_direction');
        
        // Validate date formats
        $validatedStartDate = null;
        if ($startDate) {
            $validatedStartDate = \DateTime::createFromFormat('Y-m-d', $startDate);
            if (!$validatedStartDate) {
                $this->addFlash('error', 'Неверный формат даты начала');
                $startDate = null;
            }
        }
        
        $validatedEndDate = null;
        if ($endDate) {
            $validatedEndDate = \DateTime::createFromFormat('Y-m-d', $endDate);
            if (!$validatedEndDate) {
                $this->addFlash('error', 'Неверный формат даты окончания');
                $endDate = null;
            }
        }
        
        $validatedCreatedAfter = null;
        if ($createdAfter) {
            $validatedCreatedAfter = \DateTime::createFromFormat('Y-m-d', $createdAfter);
            if (!$validatedCreatedAfter) {
                $this->addFlash('error', 'Неверный формат даты "создано после"');
                $createdAfter = null;
            }
        }
        
        $validatedCreatedBefore = null;
        if ($createdBefore) {
            $validatedCreatedBefore = \DateTime::createFromFormat('Y-m-d', $createdBefore);
            if (!$validatedCreatedBefore) {
                $this->addFlash('error', 'Неверный формат даты "создано до"');
                $createdBefore = null;
            }
        }
        
        // Prepare criteria for search
        $criteria = [];
        if ($search) {
            $criteria['search'] = $search;
        }
        if ($status) {
            $criteria['status'] = $status;
        }
        if ($priority) {
            $criteria['priority'] = $priority;
        }
        if ($categoryId) {
            $criteria['category'] = $categoryId;
        }
        if ($tagId) {
            $criteria['tag'] = $tagId;
        }
        if ($validatedStartDate) {
            $criteria['startDate'] = $validatedStartDate;
        }
        if ($validatedEndDate) {
            $criteria['endDate'] = $validatedEndDate;
        }
        if ($validatedCreatedAfter) {
            $criteria['createdAfter'] = $validatedCreatedAfter;
        }
        if ($validatedCreatedBefore) {
            $criteria['createdBefore'] = $validatedCreatedBefore;
        }
        if ($assignedToMe) {
            $criteria['assignedToMe'] = $user;
        }
        if ($createdByMe) {
            $criteria['createdByMe'] = $user;
        }
        if ($overdue) {
            $criteria['overdue'] = true;
        }
        if ($sortBy) {
            $criteria['sortBy'] = $sortBy;
        }
        if ($sortDirection) {
            $criteria['sortDirection'] = $sortDirection;
        }
        $criteria['user'] = $user;
        
        // Pagination parameters
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20))); // Max 100 items per page, min 1
        $offset = ($page - 1) * $limit;
        
        $criteria['offset'] = $offset;
        $criteria['limit'] = $limit;
        
        // Add hide completed filter to criteria
        $hideCompleted = $request->query->get('hide_completed', false);
        if ($hideCompleted) {
            $criteria['hideCompleted'] = true;
        }
        
        // Remove pagination parameters to get total count
        $countCriteria = $criteria;
        unset($countCriteria['offset'], $countCriteria['limit']);
        
        // Get total count for pagination
        $totalCount = count($taskRepository->searchTasks($countCriteria));
        
        // Perform paginated search
        $tasks = $taskRepository->searchTasks($criteria);
        
        // Calculate pagination parameters
        $totalPages = max(1, ceil($totalCount / $limit));
        
        // Get user's categories for filter dropdown
        $categories = $categoryRepository->findByUser($user);
        
        // Get all tags for filter dropdown
        $tags = $tagRepository->findByUser($user);
        
        try {
            return $this->render('task/index.html.twig', [
                'tasks' => $tasks,
                'categories' => $categories,
                'tags' => $tags,
                'searchQuery' => $search,
                'currentFilters' => [
                    'status' => $status,
                    'priority' => $priority,
                    'category' => $categoryId,
                    'tag' => $tagId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'created_after' => $createdAfter,
                    'created_before' => $createdBefore,
                    'assigned_to_me' => $assignedToMe,
                    'created_by_me' => $createdByMe,
                    'overdue' => $overdue,
                    'sort_by' => $sortBy,
                    'sort_direction' => $sortDirection
                ],
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalTasks' => $totalCount,
                'limit' => $limit
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_search');
            }
        }
    }
    
    #[Route('/export', name: 'app_task_export', methods: ['GET'])]
    public function export(
        Request $request,
        TaskRepository $taskRepository
    ): Response {
        $user = $this->getUser();
        
        // Get filter parameters
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $categoryId = $request->query->get('category');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        
        // Build query with eager loading of tags to prevent N+1 queries
        $qb = $taskRepository->createQueryBuilder('t')
            ->select('t, tg') // Select tasks and tags
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tg') // Left join to include tags
            ->andWhere('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');
        
        // Apply filters
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
        
        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')
               ->setParameter('startDate', new \DateTime($startDate));
        }
        
        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')
               ->setParameter('endDate', new \DateTime($endDate));
        }
        
        $tasks = $qb->getQuery()->getResult();
        
        // Create CSV content
        $csvContent = "ID,Название,Описание,Статус,Приоритет,Дата создания,Срок выполнения,Категория,Назначен пользователю,Теги\n";
        
        foreach ($tasks as $task) {
            // Since we eager-loaded the tags, we can directly access them
            $tagNames = [];
            foreach ($task->getTags() as $tag) {
                $tagNames[] = $tag->getName();
            }
            $tagsString = implode(', ', $tagNames);
            
            $csvContent .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $task->getId(),
                str_replace('"', '""', $task->getTitle()),
                str_replace('"', '""', strip_tags($task->getDescription() ?? '')),
                $task->getStatus(),
                $task->getPriority(),
                $task->getCreatedAt()->format('d.m.Y H:i'),
                $task->getDueDate() ? $task->getDueDate()->format('d.m.Y') : '',
                $task->getCategory() ? $task->getCategory()->getName() : '',
                $task->getAssignedUser() ? $task->getAssignedUser()->getFullName() : '',
                str_replace('"', '""', $tagsString)
            );
        }
        
        // Return CSV response
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        return $response;
    }
    
    #[Route('/export-with-tags', name: 'app_task_export_with_tags', methods: ['GET'])]
    public function exportWithTags(
        Request $request,
        TaskRepository $taskRepository
    ): Response {
        $user = $this->getUser();
        
        // Get filter parameters
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $categoryId = $request->query->get('category');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        
        // Build query with eager loading of tags
        $qb = $taskRepository->createQueryBuilder('t')
            ->select('t, tg') // Select tasks and tags
            ->leftJoin('t.user', 'u')
            ->leftJoin('t.assignedUser', 'au')
            ->leftJoin('t.category', 'c')
            ->leftJoin('t.tags', 'tg') // Left join to include tags
            ->andWhere('t.user = :user OR t.assignedUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');
        
        // Apply filters
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
        
        if ($startDate) {
            $qb->andWhere('t.createdAt >= :startDate')
               ->setParameter('startDate', new \DateTime($startDate));
        }
        
        if ($endDate) {
            $qb->andWhere('t.createdAt <= :endDate')
               ->setParameter('endDate', new \DateTime($endDate));
        }
        
        $tasks = $qb->getQuery()->getResult();
        
        // Create CSV content
        $csvContent = "ID,Название,Описание,Статус,Приоритет,Дата создания,Срок выполнения,Категория,Назначен пользователю,Теги\n";
        
        foreach ($tasks as $task) {
            // Collect tag names
            $tagNames = [];
            foreach ($task->getTags() as $tag) {
                $tagNames[] = $tag->getName();
            }
            $tagsString = implode(', ', $tagNames);
            
            $csvContent .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $task->getId(),
                str_replace('"', '""', $task->getTitle()),
                str_replace('"', '""', strip_tags($task->getDescription() ?? '')),
                $task->getStatus(),
                $task->getPriority(),
                $task->getCreatedAt()->format('d.m.Y H:i'),
                $task->getDueDate() ? $task->getDueDate()->format('d.m.Y') : '',
                $task->getCategory() ? $task->getCategory()->getName() : '',
                $task->getAssignedUser() ? $task->getAssignedUser()->getFullName() : '',
                str_replace('"', '""', $tagsString)
            );
        }
        
        // Return CSV response
        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_with_tags_export_' . date('Y-m-d_H-i-s') . '.csv"');
        
        return $response;
    }
    
    #[Route('/quick-create', name: 'app_task_quick_create', methods: ['POST'])]
    public function quickCreate(Request $request, EntityManagerInterface $entityManager, ?PerformanceMonitorService $performanceMonitor = null, ?SanitizationService $sanitizationService = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_quick_create');
        }
        $data = json_decode($request->getContent(), true);
        
        // Sanitize inputs to prevent XSS
        $rawTitle = $data['title'] ?? '';
        $title = $sanitizationService ? $sanitizationService->sanitizeInput($rawTitle) : htmlspecialchars(trim($rawTitle), ENT_QUOTES, 'UTF-8');
        
        if (empty($title)) {
            return $this->json([
                'success' => false,
                'message' => 'Название задачи не может быть пустым'
            ], 400);
        }
        
        $task = new Task();
        $task->setTitle($title);
        
        // Sanitize description
        $rawDescription = $data['description'] ?? '';
        $description = $sanitizationService ? $sanitizationService->sanitizeInput($rawDescription) : htmlspecialchars($rawDescription, ENT_QUOTES, 'UTF-8');
        $task->setDescription($description);
        
        $task->setUser($this->getUser());
        $task->setStatus('pending');
        
        // Sanitize priority
        $rawPriority = $data['priority'] ?? 'medium';
        $allowedPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = in_array($rawPriority, $allowedPriorities) ? $rawPriority : 'medium';
        $task->setPriority($priority);
        
        $task->setCreatedAt(new \DateTimeImmutable());
        
        // Set default due date to next week if not specified
        if (!empty($data['dueDate'])) {
            // Validate date format
            try {
                $dueDate = new \DateTime($data['dueDate']);
                $task->setDueDate($dueDate);
            } catch (\Exception $e) {
                // Invalid date format, skip setting due date
            }
        }
        
        // Assign to a category if provided
        if (!empty($data['category'])) {
            $category = $entityManager->find(\App\Entity\TaskCategory::class, (int)$data['category']);
            if ($category && $category->getUser()->getId() === $this->getUser()->getId()) { // Ensure user owns the category
                $task->setCategory($category);
            }
        }
        
        // Assign to a user if provided
        if (!empty($data['assignedUser'])) {
            $assignedUser = $entityManager->find(\App\Entity\User::class, (int)$data['assignedUser']);
            if ($assignedUser) {
                $task->setAssignedUser($assignedUser);
            }
        }
        
        // Add tags if provided
        if (!empty($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $tagId) {
                $tag = $entityManager->find(\App\Entity\Tag::class, (int)$tagId);
                if ($tag) {
                    $task->addTag($tag);
                }
            }
        }
        
        $entityManager->persist($task);
        $entityManager->flush();
        
        try {
            return $this->json([
                'success' => true,
                'message' => 'Задача успешно создана',
                'task' => [
                    'id' => $task->getId(),
                    'title' => $task->getTitle(),
                    'description' => $task->getDescription(),
                    'status' => $task->getStatus(),
                    'priority' => $task->getPriority(),
                    'createdAt' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
                    'dueDate' => $task->getDueDate() ? $task->getDueDate()->format('Y-m-d') : null
                ]
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_quick_create');
            }
        }
    }
    
    #[Route('/{id}/recurrence', name: 'app_task_recurrence', methods: ['GET', 'POST'])]
    public function recurrence(
        Request $request,
        Task $task,
        EntityManagerInterface $entityManager,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_recurrence');
        }
        $this->denyAccessUnlessGranted('TASK_EDIT', $task);
        
        // Check if task already has a recurrence
        $recurrence = $task->getRecurrence();
        if (!$recurrence) {
            $recurrence = new \App\Entity\TaskRecurrence();
            $recurrence->setTask($task);
            $recurrence->setUser($this->getUser());
            $task->setRecurrence($recurrence);
        }
        
        $form = $this->createForm(\App\Form\TaskRecurrenceType::class, $recurrence);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            // Update the task's user to be the same as the recurrence user
            $recurrence->setUser($this->getUser());
            $entityManager->persist($recurrence);
            $entityManager->flush();
            
            $this->addFlash('success', 'Настройки повторения задачи успешно сохранены');
            
            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }
        
        try {
            return $this->render('task/recurrence.html.twig', [
                'task' => $task,
                'form' => $form,
            ]);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_recurrence');
            }
        }
    }
    
    #[Route('/bulk-action', name: 'app_task_bulk_action', methods: ['POST'])]
    public function bulkAction(
        Request $request,
        TaskRepository $taskRepository,
        EntityManagerInterface $entityManager,
        NotificationService $notificationService,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_bulk_action');
        }
        $action = $request->request->get('action');
        $taskIds = $request->request->get('task_ids', []);
        $currentUser = $this->getUser();
            
        if (empty($taskIds) || !is_array($taskIds)) {
            $this->addFlash('error', 'Не выбраны задачи для выполнения операции.');
            return $this->redirectToRoute('app_task_index');
        }
            
        // Use the new BulkTaskOperationService for better performance
        $bulkTaskOperationService = $this->container->get(BulkTaskOperationService::class);
            
        $result = match($action) {
            'delete' => $bulkTaskOperationService->bulkDelete($taskIds, $currentUser),
            'mark_completed' => $bulkTaskOperationService->bulkUpdateStatus($taskIds, 'completed', $currentUser),
            'mark_in_progress' => $bulkTaskOperationService->bulkUpdateStatus($taskIds, 'in_progress', $currentUser),
            'mark_pending' => $bulkTaskOperationService->bulkUpdateStatus($taskIds, 'pending', $currentUser),
            'set_priority_high' => $bulkTaskOperationService->bulkUpdatePriority($taskIds, 'high', $currentUser),
            'set_priority_medium' => $bulkTaskOperationService->bulkUpdatePriority($taskIds, 'medium', $currentUser),
            'set_priority_low' => $bulkTaskOperationService->bulkUpdatePriority($taskIds, 'low', $currentUser),
            'assign_tag' => $this->handleBulkTagAssignment($taskIds, $request, $entityManager, $currentUser),
            default => ['success' => false, 'updated_count' => 0, 'failed_tasks' => []]
        };
            
        // Handle results
        $updatedCount = $result['updated_count'] ?? 0;
        $failedTasks = $result['failed_tasks'] ?? [];
            
        if ($updatedCount > 0) {
            switch ($action) {
                case 'delete':
                    $this->addFlash('success', "Успешно удалено {$updatedCount} задач(и).");
                    break;
                case 'mark_completed':
                    $this->addFlash('success', "{$updatedCount} задач(и) отмечены как выполненные.");
                    break;
                case 'mark_in_progress':
                    $this->addFlash('success', "{$updatedCount} задач(и) отмечены как в процессе выполнения.");
                    break;
                case 'mark_pending':
                    $this->addFlash('success', "{$updatedCount} задач(и) отмечены как ожидающие.");
                    break;
                case 'set_priority_high':
                    $this->addFlash('success', "{$updatedCount} задач(и) получили высокий приоритет.");
                    break;
                case 'set_priority_medium':
                    $this->addFlash('success', "{$updatedCount} задач(и) получили средний приоритет.");
                    break;
                case 'set_priority_low':
                    $this->addFlash('success', "{$updatedCount} задач(и) получили низкий приоритет.");
                    break;
                case 'assign_tag':
                    $this->addFlash('success', "Теги успешно добавлены к {$updatedCount} задач(и).");
                    break;
            }
        }
            
        if (!empty($failedTasks)) {
            $this->addFlash('warning', 'Не удалось выполнить операцию для ' . count($failedTasks) . ' задач(и) (возможно, нет прав).');
        }
            
        if ($updatedCount === 0 && empty($failedTasks)) {
            $this->addFlash('warning', 'Не удалось выполнить операцию ни для одной задачи.');
        }
            
        try {
            return $this->redirectToRoute('app_task_index');
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_bulk_action');
            }
        }
    }
        
    private function handleBulkTagAssignment(array $taskIds, Request $request, EntityManagerInterface $entityManager, \App\Entity\User $currentUser): array
    {
        $tagIds = $request->request->get('tag_ids', []);
            
        if (empty($tagIds) || !is_array($tagIds)) {
            return ['success' => true, 'updated_count' => 0, 'failed_tasks' => []];
        }
            
        // Get the BulkTaskOperationService
        $bulkTaskOperationService = $this->container->get(BulkTaskOperationService::class);
            
        return $bulkTaskOperationService->bulkAddTags($taskIds, $tagIds, $currentUser);
    }
    
    #[Route('/api/stats', name: 'app_task_stats', methods: ['GET'])]
    public function getStats(TaskRepository $taskRepository, ?PerformanceMonitorService $performanceMonitor = null): Response
    {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_api_stats');
        }
        $user = $this->getUser();
        
        // Get quick stats from repository with caching
        $stats = $taskRepository->getQuickStats($user);
        
        try {
            return $this->json($stats);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_api_stats');
            }
        }
    }
    
    #[Route('/api/insights', name: 'app_task_insights', methods: ['GET'])]
    public function getInsights(
        TaskRepository $taskRepository,
        \App\Service\TaskInsightsService $taskInsightsService,
        ?PerformanceMonitorService $performanceMonitor = null
    ): Response {
        if ($performanceMonitor) {
            $performanceMonitor->startTimer('task_controller_api_insights');
        }
        
        $user = $this->getUser();
        
        // Get comprehensive insights
        $insights = $taskInsightsService->getDashboardInsights($user);
        
        try {
            return $this->json($insights);
        } finally {
            if ($performanceMonitor) {
                $performanceMonitor->stopTimer('task_controller_api_insights');
            }
        }
    }
}