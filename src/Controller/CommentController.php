<?php

namespace App\Controller;

use App\DTO\CreateCommentDTO;
use App\DTO\UpdateCommentDTO;
use App\Entity\Comment;
use App\Entity\Task;
use App\Form\CommentType;
use App\Service\CommentCommandService;
use App\Service\MentionService;
use App\Service\NotificationService;
use App\Service\PerformanceMonitorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/comment')]
#[IsGranted('ROLE_USER')]
class CommentController extends AbstractController
{
    public function __construct(
        private CommentCommandService $commentCommandService,
    ) {
    }

    #[Route('/task/{taskId}', name: 'app_comment_create', methods: ['POST'])]
    public function create(
        int $taskId,
        Request $request,
        NotificationService $notificationService,
        ?MentionService $mentionService = null,
        ?PerformanceMonitorService $performanceMonitor = null,
    ): Response {
        $performanceMonitor?->startTiming('comment_controller_create');

        try {
            $task = $this->getDoctrine()->getRepository(Task::class)->find($taskId);

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
                // Sanitize content to prevent XSS
                $content = $comment->getContent();
                $sanitizedContent = strip_tags($content, '<p><br><strong><em><ul><ol><li><a>');
                $comment->setContent($sanitizedContent);

                // Используем сервис для создания комментария (с Domain Events)
                $this->commentCommandService->addComment(
                    CreateCommentDTO::fromArray([
                        'taskId' => $taskId,
                        'content' => $sanitizedContent,
                    ]),
                    $this->getUser()
                );

                // Process mentions (@username)
                if ($mentionService) {
                    $mentionedUsers = $mentionService->extractMentions($sanitizedContent);
                    foreach ($mentionedUsers as $mentionedUser) {
                        if ($mentionedUser->getId() !== $this->getUser()->getId()) {
                            $notificationService->sendMentionNotification(
                                $mentionedUser,
                                $this->getUser(),
                                $task->getId(),
                                $task->getTitle(),
                                $sanitizedContent
                            );
                        }
                    }
                }

                // Notify task participants about new comment
                $this->notifyTaskParticipants($task, $comment, $notificationService);

                $this->addFlash('success', 'Комментарий успешно добавлен.');
            } else {
                if ($form->isSubmitted()) {
                    $this->addFlash('error', 'Ошибка при добавлении комментария. Проверьте введенные данные.');
                }
            }

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        } finally {
            $performanceMonitor?->stopTiming('comment_controller_create');
        }
    }

    #[Route('/{id}/edit', name: 'app_comment_edit', methods: ['GET', 'POST'])]
    public function edit(
        Comment $comment,
        Request $request,
        ?PerformanceMonitorService $performanceMonitor = null,
    ): Response {
        $performanceMonitor?->startTiming('comment_controller_edit');

        try {
            // Check if user can edit this comment (must be author or admin)
            if ($comment->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('У вас нет прав для редактирования этого комментария.');
            }

            $form = $this->createForm(CommentType::class, $comment);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Sanitize content to prevent XSS
                $content = $comment->getContent();
                $sanitizedContent = strip_tags($content, '<p><br><strong><em><ul><ol><li><a>');

                // Используем сервис для обновления комментария (с Domain Events)
                $this->commentCommandService->updateComment(
                    UpdateCommentDTO::fromArray([
                        'id' => $comment->getId(),
                        'content' => $sanitizedContent,
                    ]),
                    $this->getUser()
                );

                $this->addFlash('success', 'Комментарий успешно обновлен.');
                return $this->redirectToRoute('app_task_show', ['id' => $comment->getTask()->getId()]);
            }

            return $this->render('comment/edit.html.twig', [
                'comment' => $comment,
                'form' => $form,
            ]);
        } finally {
            $performanceMonitor?->stopTiming('comment_controller_edit');
        }
    }

    #[Route('/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function delete(
        Comment $comment,
        Request $request,
        ?PerformanceMonitorService $performanceMonitor = null,
    ): Response {
        $performanceMonitor?->startTiming('comment_controller_delete');

        try {
            // Check if user can delete this comment (must be author or admin)
            if ($comment->getAuthor() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('У вас нет прав для удаления этого комментария.');
            }

            $taskId = $comment->getTask()->getId();

            if ($this->isCsrfTokenValid('delete' . $comment->getId(), $request->request->get('_token'))) {
                // Используем сервис для удаления комментария (с Domain Events)
                $this->commentCommandService->removeComment($comment->getId(), $this->getUser());

                $this->addFlash('success', 'Комментарий успешно удален.');
            } else {
                $this->addFlash('error', 'Неверный токен безопасности.');
            }

            return $this->redirectToRoute('app_task_show', ['id' => $taskId]);
        } finally {
            $performanceMonitor?->stopTiming('comment_controller_delete');
        }
    }

    /**
     * Notify relevant users about new comment
     */
    private function notifyTaskParticipants(Task $task, Comment $comment, NotificationService $notificationService): void
    {
        $currentUser = $this->getUser();
        $notifiedUsers = [];

        // Notify task creator
        if ($task->getUser() && $task->getUser()->getId() !== $currentUser->getId()) {
            $notificationService->sendCommentNotification(
                $task->getUser(),
                $currentUser,
                $task->getId(),
                $task->getTitle(),
                $comment->getContent()
            );
            $notifiedUsers[] = $task->getUser()->getId();
        }

        // Notify assigned user
        if ($task->getAssignedUser() &&
            $task->getAssignedUser()->getId() !== $currentUser->getId() &&
            !in_array($task->getAssignedUser()->getId(), $notifiedUsers)) {
            $notificationService->sendCommentNotification(
                $task->getAssignedUser(),
                $currentUser,
                $task->getId(),
                $task->getTitle(),
                $comment->getContent()
            );
            $notifiedUsers[] = $task->getAssignedUser()->getId();
        }

        // Notify other commenters (excluding current user and already notified)
        foreach ($task->getComments() as $existingComment) {
            $author = $existingComment->getAuthor();
            if ($author &&
                $author->getId() !== $currentUser->getId() &&
                !in_array($author->getId(), $notifiedUsers)) {
                $notificationService->sendCommentNotification(
                    $author,
                    $currentUser,
                    $task->getId(),
                    $task->getTitle(),
                    $comment->getContent()
                );
                $notifiedUsers[] = $author->getId();
            }
        }
    }
}
