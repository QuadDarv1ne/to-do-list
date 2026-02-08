<?php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tasks')]
#[IsGranted('ROLE_USER')]
class TaskController extends AbstractController
{
    #[Route('/', name: 'app_task_index', methods: ['GET'])]
    public function index(TaskRepository $taskRepository, Request $request): Response
    {
        $user = $this->getUser();
        
        // Получаем параметры фильтрации
        $status = $request->query->get('status');
        $assignedUser = $request->query->get('assignedUser');
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');
        
        // Формируем критерии поиска
        $criteria = [];
        
        if ($this->isGranted('ROLE_ADMIN')) {
            // Администратор видит все задачи, может фильтровать по пользователю
            if ($assignedUser && $assignedUser !== 'all') {
                $criteria['assignedUser'] = $assignedUser;
            }
        } else {
            // Обычный пользователь видит только свои задачи
            $criteria['assignedUser'] = $user;
        }
        
        // Фильтр по статусу
        if ($status !== null && $status !== 'all') {
            $criteria['isDone'] = $status === 'done';
        }
        
        // Получаем задачи
        $queryBuilder = $taskRepository->createQueryBuilder('t')
            ->where('1=1'); // Начинаем с всегда истинного условия
        
        // Применяем критерии
        foreach ($criteria as $field => $value) {
            $queryBuilder->andWhere("t.$field = :$field")
                ->setParameter($field, $value);
        }
        
        // Фильтр по дате
        if ($dateFrom) {
            $queryBuilder->andWhere('t.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($dateFrom));
        }
        
        if ($dateTo) {
            $dateToObj = new \DateTime($dateTo);
            $dateToObj->modify('+1 day'); // Включаем весь день
            $queryBuilder->andWhere('t.createdAt < :dateTo')
                ->setParameter('dateTo', $dateToObj);
        }
        
        // Сортировка
        $queryBuilder->orderBy('t.createdAt', 'DESC');
        
        $tasks = $queryBuilder->getQuery()->getResult();
        
        // Для администратора получаем список пользователей для фильтра
        $usersList = [];
        if ($this->isGranted('ROLE_ADMIN')) {
            $usersList = $taskRepository->getEntityManager()
                ->getRepository(User::class)
                ->findBy([], ['lastName' => 'ASC']);
        }
        
        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
            'statusFilter' => $status,
            'assignedUserFilter' => $assignedUser,
            'dateFromFilter' => $dateFrom,
            'dateToFilter' => $dateTo,
            'usersList' => $usersList,
        ]);
    }

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, NotificationService $notificationService): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setCreatedAt(new \DateTimeImmutable());
            $task->setUpdateAt(new \DateTimeImmutable());
            
            // Если пользователь не выбрал исполнителя, назначаем текущего пользователя
            if (!$task->getAssignedUser()) {
                $task->setAssignedUser($this->getUser());
            }
            
            $entityManager->persist($task);
            $entityManager->flush();

            // Создаем уведомление для назначенного пользователя
            if ($task->getAssignedUser() && $task->getAssignedUser() !== $this->getUser()) {
                $notificationService->notifyTaskAssignment($task, $this->getUser());
            }

            $this->addFlash('success', 'Задача успешно создана');

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/new.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_show', methods: ['GET'])]
    public function show(Task $task): Response
    {
        // Проверяем права доступа к задаче
        if (!$this->isGranted('ROLE_ADMIN') && $task->getAssignedUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('У вас нет доступа к этой задаче.');
        }
        
        return $this->render('task/show.html.twig', [
            'task' => $task,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_task_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Task $task, EntityManagerInterface $entityManager, NotificationService $notificationService): Response
    {
        // Проверяем права доступа к задаче
        if (!$this->isGranted('ROLE_ADMIN') && $task->getAssignedUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('У вас нет прав для редактирования этой задачи.');
        }
        
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setUpdateAt(new \DateTimeImmutable());
            
            // Проверяем, изменился ли пользователь, которому назначена задача
            $originalAssignedUser = $entityManager->getUnitOfWork()->getOriginalEntityData($task)['assigned_user_id'] ?? null;
            $newAssignedUser = $task->getAssignedUser()?->getId();
            
            $entityManager->flush();

            // Создаем уведомление для нового назначенного пользователя
            if ($newAssignedUser && $newAssignedUser != $originalAssignedUser) {
                $notificationService->notifyTaskReassignment($task, $this->getUser());
            }

            $this->addFlash('success', 'Задача успешно обновлена');

            return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_task_delete', methods: ['POST'])]
    public function delete(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        // Проверяем права доступа к задаче
        if (!$this->isGranted('ROLE_ADMIN') && $task->getAssignedUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('У вас нет прав для удаления этой задачи.');
        }
        
        if ($this->isCsrfTokenValid('delete'.$task->getId(), $request->request->get('_token'))) {
            $entityManager->remove($task);
            $entityManager->flush();
            
            $this->addFlash('success', 'Задача успешно удалена');
        }

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }
    
    #[Route('/{id}/toggle', name: 'app_task_toggle', methods: ['POST'])]
    public function toggle(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        // Проверяем права доступа к задаче
        if (!$this->isGranted('ROLE_ADMIN') && $task->getAssignedUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('У вас нет прав для изменения статуса этой задачи.');
        }
        
        if ($this->isCsrfTokenValid('toggle'.$task->getId(), $request->request->get('_token'))) {
            $task->setIsDone(!$task->isDone());
            $task->setUpdateAt(new \DateTimeImmutable());
            $entityManager->flush();
            
            $status = $task->isDone() ? 'выполнена' : 'возвращена в работу';
            $this->addFlash('success', "Задача {$status}");
        }

        return $this->redirectToRoute('app_task_index', [], Response::HTTP_SEE_OTHER);
    }
    
    #[Route('/export', name: 'app_task_export', methods: ['GET'])]
    public function export(TaskRepository $taskRepository, Request $request): Response
    {
        $user = $this->getUser();
        
        // Получаем параметры фильтрации (те же, что и в index)
        $status = $request->query->get('status');
        $assignedUser = $request->query->get('assignedUser');
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');
        
        // Формируем критерии поиска
        $criteria = [];
        
        if ($this->isGranted('ROLE_ADMIN')) {
            // Администратор видит все задачи, может фильтровать по пользователю
            if ($assignedUser && $assignedUser !== 'all') {
                $criteria['assignedUser'] = $assignedUser;
            }
        } else {
            // Обычный пользователь видит только свои задачи
            $criteria['assignedUser'] = $user;
        }
        
        // Фильтр по статусу
        if ($status !== null && $status !== 'all') {
            $criteria['isDone'] = $status === 'done';
        }
        
        // Получаем задачи
        $queryBuilder = $taskRepository->createQueryBuilder('t')
            ->where('1=1'); // Начинаем с всегда истинного условия
        
        // Применяем критерии
        foreach ($criteria as $field => $value) {
            $queryBuilder->andWhere("t.$field = :$field")
                ->setParameter($field, $value);
        }
        
        // Фильтр по дате
        if ($dateFrom) {
            $queryBuilder->andWhere('t.createdAt >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($dateFrom));
        }
        
        if ($dateTo) {
            $dateToObj = new \DateTime($dateTo);
            $dateToObj->modify('+1 day'); // Включаем весь день
            $queryBuilder->andWhere('t.createdAt < :dateTo')
                ->setParameter('dateTo', $dateToObj);
        }
        
        // Сортировка
        $queryBuilder->orderBy('t.createdAt', 'DESC');
        
        $tasks = $queryBuilder->getQuery()->getResult();
        
        // Подготавливаем CSV
        $response = new Response();
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="tasks_export.csv"');
        
        $handle = fopen('php://memory', 'r+');
        
        // Заголовки CSV
        fputcsv($handle, [
            'ID',
            'Название',
            'Описание',
            'Статус',
            'Приоритет',
            'Срок выполнения',
            'Назначена',
            'Дата создания',
            'Дата обновления'
        ], ';');
        
        // Данные
        foreach ($tasks as $task) {
            fputcsv($handle, [
                $task->getId(),
                $task->getName(),
                $task->getDescription(),
                $task->isDone() ? 'Выполнена' : 'В работе',
                $task->getPriorityLabel(),
                $task->getDeadline() ? $task->getDeadline()->format('d.m.Y') : '',
                $task->getAssignedUser() ? $task->getAssignedUser()->getFullName() : 'Не назначена',
                $task->getCreatedAt()->format('d.m.Y H:i'),
                $task->getUpdateAt()->format('d.m.Y H:i')
            ], ';');
        }
        
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        
        $response->setContent($content);
        
        return $response;
    }
}