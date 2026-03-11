<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Entity\Client;
use App\Entity\Deal;
use App\Entity\Document;
use App\Entity\Comment;
use Doctrine\ORM\EntityManagerInterface;

class GlobalSearchService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Global search across all entities
     */
    public function search(string $query, ?User $user = null, array $options = []): array
    {
        $query = trim($query);
        
        if (strlen($query) < 2) {
            return ['error' => 'Query too short'];
        }

        $results = [
            'query' => $query,
            'tasks' => [],
            'clients' => [],
            'deals' => [],
            'documents' => [],
            'comments' => [],
            'users' => [],
            'total' => 0,
        ];

        $limit = $options['limit'] ?? 10;

        $results['tasks'] = $this->searchTasks($query, $user, $limit);
        $results['clients'] = $this->searchClients($query, $limit);
        $results['deals'] = $this->searchDeals($query, $user, $limit);
        $results['documents'] = $this->searchDocuments($query, $user, $limit);
        $results['comments'] = $this->searchComments($query, $user, $limit);
        $results['users'] = $this->searchUsers($query, $limit);

        $results['total'] = 
            count($results['tasks']) + 
            count($results['clients']) + 
            count($results['deals']) + 
            count($results['documents']) + 
            count($results['comments']) + 
            count($results['users']);

        return $results;
    }

    private function searchTasks(string $query, ?User $user, int $limit): array
    {
        $qb = $this->entityManager->getRepository(Task::class)->createQueryBuilder('t');
        
        $qb->where('t.title LIKE :query')
           ->orWhere('t.description LIKE :query')
           ->setParameter('query', '%' . $query . '%')
           ->setMaxResults($limit);

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $qb->andWhere('t.user = :user OR t.assignedUser = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    private function searchClients(string $query, int $limit): array
    {
        if (!class_exists(Client::class)) {
            return [];
        }

        $qb = $this->entityManager->getRepository(Client::class)->createQueryBuilder('c');
        
        $qb->where('c.name LIKE :query')
           ->orWhere('c.email LIKE :query')
           ->orWhere('c.phone LIKE :query')
           ->setParameter('query', '%' . $query . '%')
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    private function searchDeals(string $query, ?User $user, int $limit): array
    {
        if (!class_exists(Deal::class)) {
            return [];
        }

        $qb = $this->entityManager->getRepository(Deal::class)->createQueryBuilder('d');
        
        $qb->where('d.title LIKE :query')
           ->orWhere('d.description LIKE :query')
           ->setParameter('query', '%' . $query . '%')
           ->setMaxResults($limit);

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $qb->andWhere('d.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    private function searchDocuments(string $query, ?User $user, int $limit): array
    {
        if (!class_exists(Document::class)) {
            return [];
        }

        $qb = $this->entityManager->getRepository(Document::class)->createQueryBuilder('d');
        
        $qb->where('d.title LIKE :query')
           ->orWhere('d.description LIKE :query')
           ->setParameter('query', '%' . $query . '%')
           ->setMaxResults($limit);

        if ($user && !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $qb->andWhere('d.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    private function searchComments(string $query, ?User $user, int $limit): array
    {
        if (!class_exists(Comment::class)) {
            return [];
        }

        $qb = $this->entityManager->getRepository(Comment::class)->createQueryBuilder('c');
        
        $qb->where('c.content LIKE :query')
           ->setParameter('query', '%' . $query . '%')
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    private function searchUsers(string $query, int $limit): array
    {
        $qb = $this->entityManager->getRepository(User::class)->createQueryBuilder('u');
        
        $qb->where('u.username LIKE :query')
           ->orWhere('u.email LIKE :query')
           ->orWhere('u.firstName LIKE :query')
           ->orWhere('u.lastName LIKE :query')
           ->setParameter('query', '%' . $query . '%')
           ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Search suggestions for autocomplete
     */
    public function getSuggestions(string $query, int $limit = 5): array
    {
        $query = trim($query);
        
        if (strlen($query) < 2) {
            return [];
        }

        $suggestions = [];

        // Task suggestions
        $tasks = $this->searchTasks($query, null, $limit);
        foreach ($tasks as $task) {
            $suggestions[] = [
                'type' => 'task',
                'id' => $task->getId(),
                'label' => $task->getTitle(),
                'url' => '/tasks/' . $task->getId(),
            ];
        }

        // User suggestions
        $users = $this->searchUsers($query, $limit);
        foreach ($users as $user) {
            $suggestions[] = [
                'type' => 'user',
                'id' => $user->getId(),
                'label' => $user->getDisplayName() ?? $user->getEmail(),
                'url' => '/users/' . $user->getId(),
            ];
        }

        return array_slice($suggestions, 0, $limit * 2);
    }
}
