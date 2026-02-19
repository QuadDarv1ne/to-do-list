<?php

namespace App\Service;

use App\Entity\Tag;
use App\Entity\Task;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

class TagManagementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * Find or create tag by name
     */
    public function findOrCreateTag(string $name): Tag
    {
        $name = trim($name);

        // Try to find existing tag (case-insensitive)
        $tag = $this->tagRepository->findOneBy(['name' => $name]);

        if (!$tag) {
            $tag = new Tag();
            $tag->setName($name);
            $tag->setSlug($this->generateSlug($name));
            $tag->setColor($this->generateRandomColor());

            $this->entityManager->persist($tag);
            $this->entityManager->flush();
        }

        return $tag;
    }

    /**
     * Parse tags from string (comma or space separated)
     */
    public function parseTagsFromString(string $tagsString): array
    {
        // Split by comma or space
        $tagNames = preg_split('/[,\\s]+/', $tagsString, -1, PREG_SPLIT_NO_EMPTY);

        $tags = [];
        foreach ($tagNames as $tagName) {
            $tagName = trim($tagName, '#'); // Remove # if present
            if (!empty($tagName)) {
                $tags[] = $this->findOrCreateTag($tagName);
            }
        }

        return $tags;
    }

    /**
     * Add tags to task
     */
    public function addTagsToTask(Task $task, array $tags): void
    {
        foreach ($tags as $tag) {
            if (!$task->getTags()->contains($tag)) {
                $task->addTag($tag);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Remove tag from task
     */
    public function removeTagFromTask(Task $task, Tag $tag): void
    {
        $task->removeTag($tag);
        $this->entityManager->flush();
    }

    /**
     * Get popular tags
     */
    public function getPopularTags(int $limit = 20): array
    {
        return $this->tagRepository->createQueryBuilder('t')
            ->select('t, COUNT(task.id) as task_count')
            ->leftJoin('t.tasks', 'task')
            ->groupBy('t.id')
            ->orderBy('task_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get tags for autocomplete
     */
    public function searchTags(string $query, int $limit = 10): array
    {
        return $this->tagRepository->createQueryBuilder('t')
            ->where('t.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get tag statistics
     */
    public function getTagStatistics(Tag $tag): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        // Total tasks with this tag
        $totalTasks = $qb->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->join('t.tags', 'tag')
            ->where('tag.id = :tagId')
            ->setParameter('tagId', $tag->getId())
            ->getQuery()
            ->getSingleScalarResult();

        // Completed tasks
        $completedTasks = $this->entityManager->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->join('t.tags', 'tag')
            ->where('tag.id = :tagId')
            ->andWhere('t.status = :completed')
            ->setParameter('tagId', $tag->getId())
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Average priority
        $avgPriority = $this->calculateAveragePriority($tag);

        return [
            'total_tasks' => (int)$totalTasks,
            'completed_tasks' => (int)$completedTasks,
            'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
            'avg_priority' => $avgPriority,
        ];
    }

    /**
     * Get related tags (tags that often appear together)
     */
    public function getRelatedTags(Tag $tag, int $limit = 5): array
    {
        $qb = $this->entityManager->createQueryBuilder();

        return $qb->select('related_tag, COUNT(t.id) as co_occurrence')
            ->from(Task::class, 't')
            ->join('t.tags', 'tag')
            ->join('t.tags', 'related_tag')
            ->where('tag.id = :tagId')
            ->andWhere('related_tag.id != :tagId')
            ->groupBy('related_tag.id')
            ->orderBy('co_occurrence', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('tagId', $tag->getId())
            ->getQuery()
            ->getResult();
    }

    /**
     * Merge tags
     */
    public function mergeTags(Tag $sourceTag, Tag $targetTag): void
    {
        // Move all tasks from source to target
        $tasks = $sourceTag->getTasks();

        foreach ($tasks as $task) {
            $task->removeTag($sourceTag);
            if (!$task->getTags()->contains($targetTag)) {
                $task->addTag($targetTag);
            }
        }

        // Delete source tag
        $this->entityManager->remove($sourceTag);
        $this->entityManager->flush();
    }

    /**
     * Delete unused tags
     */
    public function deleteUnusedTags(): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        $unusedTags = $qb->select('t')
            ->from(Tag::class, 't')
            ->leftJoin('t.tasks', 'task')
            ->groupBy('t.id')
            ->having('COUNT(task.id) = 0')
            ->getQuery()
            ->getResult();

        $count = \count($unusedTags);

        foreach ($unusedTags as $tag) {
            $this->entityManager->remove($tag);
        }

        $this->entityManager->flush();

        return $count;
    }

    /**
     * Get tag cloud data
     */
    public function getTagCloud(): array
    {
        $tags = $this->tagRepository->createQueryBuilder('t')
            ->select('t.id, t.name, t.color, COUNT(task.id) as task_count')
            ->leftJoin('t.tasks', 'task')
            ->groupBy('t.id, t.name, t.color')
            ->having('COUNT(task.id) > 0')
            ->orderBy('task_count', 'DESC')
            ->getQuery()
            ->getResult();

        // Calculate font sizes based on task count
        if (empty($tags)) {
            return [];
        }

        $maxCount = max(array_column($tags, 'task_count'));
        $minCount = min(array_column($tags, 'task_count'));

        foreach ($tags as &$tag) {
            $tag['size'] = $this->calculateTagSize($tag['task_count'], $minCount, $maxCount);
        }

        return $tags;
    }

    /**
     * Generate slug from name
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Generate random color for tag
     */
    private function generateRandomColor(): string
    {
        $colors = [
            '#007bff', '#6c757d', '#28a745', '#dc3545', '#ffc107',
            '#17a2b8', '#6f42c1', '#e83e8c', '#fd7e14', '#20c997',
        ];

        return $colors[array_rand($colors)];
    }

    /**
     * Calculate average priority
     */
    private function calculateAveragePriority(Tag $tag): string
    {
        $priorityValues = [
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            'urgent' => 4,
        ];

        $tasks = $this->entityManager->createQueryBuilder()
            ->select('t.priority')
            ->from(Task::class, 't')
            ->join('t.tags', 'tag')
            ->where('tag.id = :tagId')
            ->setParameter('tagId', $tag->getId())
            ->getQuery()
            ->getResult();

        if (empty($tasks)) {
            return 'medium';
        }

        $sum = 0;
        foreach ($tasks as $task) {
            $sum += $priorityValues[$task['priority']] ?? 2;
        }

        $avg = $sum / \count($tasks);

        if ($avg <= 1.5) {
            return 'low';
        }
        if ($avg <= 2.5) {
            return 'medium';
        }
        if ($avg <= 3.5) {
            return 'high';
        }

        return 'urgent';
    }

    /**
     * Calculate tag size for cloud
     */
    private function calculateTagSize(int $count, int $min, int $max): int
    {
        if ($max === $min) {
            return 16;
        }

        // Scale between 12px and 32px
        $minSize = 12;
        $maxSize = 32;

        $size = $minSize + (($count - $min) / ($max - $min)) * ($maxSize - $minSize);

        return (int)round($size);
    }
}
