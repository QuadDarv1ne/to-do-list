<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Task;
use App\Form\CommentType;
use App\Repository\CommentRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/comment')]
class CommentController extends AbstractController
{
    #[Route('/task/{taskId}', name: 'app_comment_create', methods: ['POST'])]
    public function create(int $taskId, Request $request, EntityManagerInterface $entityManager, NotificationService $notificationService): Response
    {
        $task = $entityManager->getRepository(Task::class)->find($taskId);
        
        if (!$task) {
            $this->addFlash('error', 'Задача не найдена.');
            return $this->redirectToRoute('app_task_index');
        }

        // Check if user can comment on this task
        if (!$this->isGranted('TASK_COMMENT', $task)) {
            throw $this->createAccessDeniedException('У вас нет прав для комментирования этой задачи.');
        }

        $comment = new Comment();
        $comment->setTask($task);
        $comment->setAuthor($this->getUser());

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($comment);
            $entityManager->flush();

            // Send notification about new comment
            $notificationService->notifyTaskComment($task, $this->getUser(), $comment->getContent());

            $this->addFlash('success', 'Комментарий успешно добавлен.');

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function delete(Comment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$comment) {
            $this->addFlash('error', 'Комментарий не найден.');
            return $this->redirectToRoute('app_task_index');
        }

        // Check if user can delete this comment (must be author or admin)
        if ($comment->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('У вас нет прав для удаления этого комментария.');
        }

        $taskId = $comment->getTask()->getId();
        
        if ($this->isCsrfTokenValid('delete' . $comment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($comment);
            $entityManager->flush();

            $this->addFlash('success', 'Комментарий успешно удален.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $taskId]);
    }
}