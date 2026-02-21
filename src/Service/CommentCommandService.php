<?php

namespace App\Service;

use App\Domain\Comment\Event\CommentAdded;
use App\Domain\Comment\Event\CommentRemoved;
use App\Domain\Comment\Event\CommentUpdated;
use App\DTO\CreateCommentDTO;
use App\DTO\UpdateCommentDTO;
use App\Entity\Comment;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Сервис для управления комментариями с использованием DTO
 */
final class CommentCommandService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommentRepository $commentRepository,
        private TaskRepository $taskRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Добавить комментарий
     */
    public function addComment(CreateCommentDTO $dto, User $author): Comment
    {
        $task = $this->taskRepository->find($dto->getTaskId());

        if (!$task) {
            throw new \InvalidArgumentException(sprintf('Task with id %d not found', $dto->getTaskId()));
        }

        $comment = new Comment();
        $comment->setTask($task);
        $comment->setAuthor($author);
        $comment->setContent($dto->getContent());

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        // Отправляем Domain Event (после flush, когда ID уже присвоен)
        $this->dispatchCommentAdded($comment, $author);

        return $comment;
    }

    /**
     * Обновить комментарий
     */
    public function updateComment(UpdateCommentDTO $dto, User $updater): Comment
    {
        $comment = $this->commentRepository->find($dto->getId());

        if (!$comment) {
            throw new \InvalidArgumentException(sprintf('Comment with id %d not found', $dto->getId()));
        }

        // Обновляем поля из DTO
        if ($dto->getContent() !== null) {
            $comment->setContent($dto->getContent());
        }

        $comment->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        // Отправляем Domain Event
        $this->dispatchCommentUpdated($comment, $updater);

        return $comment;
    }

    /**
     * Удалить комментарий
     */
    public function removeComment(int $commentId, User $remover): void
    {
        $comment = $this->commentRepository->find($commentId);

        if (!$comment) {
            throw new \InvalidArgumentException(sprintf('Comment with id %d not found', $commentId));
        }

        $taskId = $comment->getTask()->getId();
        $authorId = $comment->getAuthor()->getId();

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        // Отправляем Domain Event
        $this->dispatchCommentRemoved($commentId, $taskId, $authorId, $remover);
    }

    /**
     * Отправить Domain Event о добавлении комментария
     */
    private function dispatchCommentAdded(Comment $comment, User $author): void
    {
        $event = CommentAdded::create(
            $comment->getId(),
            $comment->getTask()->getId(),
            $comment->getContent(),
            $author->getId(),
        );

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * Отправить Domain Event об обновлении комментария
     */
    private function dispatchCommentUpdated(Comment $comment, User $updater): void
    {
        $event = CommentUpdated::create(
            $comment->getId(),
            $comment->getTask()->getId(),
            $comment->getContent(),
            $updater->getId(),
        );

        $this->eventDispatcher->dispatch($event);
    }

    /**
     * Отправить Domain Event об удалении комментария
     */
    private function dispatchCommentRemoved(int $commentId, int $taskId, int $authorId, User $remover): void
    {
        $event = CommentRemoved::create(
            $commentId,
            $taskId,
            $authorId,
            $remover->getId(),
        );

        $this->eventDispatcher->dispatch($event);
    }
}
