<?php

namespace App\Controller\Api;

use App\Repository\TaskRepository;
use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/quick-search', name: 'api_quick_search')]
class QuickSearchApiController extends AbstractController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private ClientRepository $clientRepository,
        private DealRepository $dealRepository
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $limit = (int) $request->query->get('limit', 10);

        if (strlen($query) < 2) {
            return $this->json(['results' => []]);
        }

        $results = [];

        // Поиск задач
        $tasks = $this->taskRepository->createQueryBuilder('t')
            ->where('t.title LIKE :query')
            ->setParameter('query', "%{$query}%")
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        foreach ($tasks as $task) {
            $results[] = [
                'title' => $task->getTitle(),
                'subtitle' => $task->getStatus()?->getName() ?? 'Без статуса',
                'icon' => 'tasks',
                'url' => $this->generateUrl('app_task_show', ['id' => $task->getId()]),
                'query' => $query,
            ];
        }

        // Поиск клиентов
        $clients = $this->clientRepository->createQueryBuilder('c')
            ->where('c.name LIKE :query')
            ->orWhere('c.email LIKE :query')
            ->orWhere('c.phone LIKE :query')
            ->setParameter('query', "%{$query}%")
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        foreach ($clients as $client) {
            $results[] = [
                'title' => $client->getName(),
                'subtitle' => $client->getEmail() ?? $client->getPhone() ?? 'Контакт',
                'icon' => 'user',
                'url' => $this->generateUrl('app_clients_show', ['id' => $client->getId()]),
                'query' => $query,
            ];
        }

        // Поиск сделок
        $deals = $this->dealRepository->createQueryBuilder('d')
            ->where('d.name LIKE :query')
            ->setParameter('query', "%{$query}%")
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        foreach ($deals as $deal) {
            $results[] = [
                'title' => $deal->getName(),
                'subtitle' => $deal->getStatus()?->getName() ?? 'Без статуса',
                'icon' => 'handshake',
                'url' => $this->generateUrl('app_deals_show', ['id' => $deal->getId()]),
                'query' => $query,
            ];
        }

        // Ограничиваем общее количество
        $results = array_slice($results, 0, $limit);

        return $this->json([
            'results' => $results,
            'total' => count($results),
        ]);
    }
}
