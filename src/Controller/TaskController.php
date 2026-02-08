<?php
// src/Controller/TaskController.php

namespace App\Controller;

use App\Entity\Task;
use App\Form\TaskType;
use App\Repository\TaskRepository;
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
    public function index(TaskRepository $taskRepository): Response
    {
        $user = $this->getUser();
        
        // Показываем только задачи пользователя или все задачи для администратора
        if ($this->isGranted('ROLE_ADMIN')) {
            $tasks = $taskRepository->findBy([], ['createdAt' => 'DESC']);
        } else {
            $tasks = $taskRepository->findBy(['assignedUser' => $user], ['createdAt' => 'DESC']);
        }

        return $this->render('task/index.html.twig', [
            'tasks' => $tasks,
        ]);
    }

    #[Route('/new', name: 'app_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = new Task();
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setCreatedAt(new \DateTimeImmutable());
            $task->setUpdateAt(new \DateTimeImmutable());
            
            // Привязываем задачу к текущему пользователю
            $task->setAssignedUser($this->getUser());
            
            $entityManager->persist($task);
            $entityManager->flush();

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
    public function edit(Request $request, Task $task, EntityManagerInterface $entityManager): Response
    {
        // Проверяем права доступа к задаче
        if (!$this->isGranted('ROLE_ADMIN') && $task->getAssignedUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('У вас нет прав для редактирования этой задачи.');
        }
        
        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $task->setUpdateAt(new \DateTimeImmutable());
            $entityManager->flush();

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
}