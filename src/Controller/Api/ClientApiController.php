<?php

namespace App\Controller\Api;

use App\Repository\ClientRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/clients')]
#[IsGranted('ROLE_USER')]
class ClientApiController extends AbstractController
{
    #[Route('', name: 'api_clients_list', methods: ['GET'])]
    public function list(ClientRepository $clientRepository, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $segment = $request->query->get('segment');
        $category = $request->query->get('category');

        $manager = $this->isGranted('ROLE_ADMIN') ? null : $user;

        // Use optimized repository methods based on filters
        if ($segment && $category) {
            // If both filters, get by segment first then filter by category
            $clients = $clientRepository->findBySegment($segment, $manager);
            $clients = array_filter($clients, fn ($client) => $client->getCategory() === $category);
        } elseif ($segment) {
            $clients = $clientRepository->findBySegment($segment, $manager);
        } elseif ($category) {
            $clients = $clientRepository->findByCategory($category, $manager);
        } else {
            // Use optimized method with joins
            $clients = $this->isGranted('ROLE_ADMIN')
                ? $clientRepository->findAllWithRelations()
                : $clientRepository->findByManager($user);
        }

        $data = array_map(function ($client) {
            return [
                'id' => $client->getId(),
                'company_name' => $client->getCompanyName(),
                'inn' => $client->getInn(),
                'contact_person' => $client->getContactPerson(),
                'phone' => $client->getPhone(),
                'email' => $client->getEmail(),
                'segment' => $client->getSegment(),
                'category' => $client->getCategory(),
                'total_revenue' => (float) $client->getTotalRevenue(),
                'completed_deals_count' => $client->getCompletedDealsCount(),
                'created_at' => $client->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }, $clients);

        return $this->json([
            'success' => true,
            'data' => array_values($data),
            'count' => \count($data),
        ]);
    }

    #[Route('/search', name: 'api_clients_search', methods: ['GET'])]
    public function search(ClientRepository $clientRepository, Request $request): JsonResponse
    {
        $query = $request->query->get('q', '');
        $user = $this->getUser();

        if (\strlen($query) < 2) {
            return $this->json([
                'success' => false,
                'error' => 'Query must be at least 2 characters',
            ], 400);
        }

        $manager = $this->isGranted('ROLE_ADMIN') ? null : $user;
        $clients = $clientRepository->searchByName($query, $manager);

        $data = array_map(function ($client) {
            return [
                'id' => $client->getId(),
                'company_name' => $client->getCompanyName(),
                'contact_person' => $client->getContactPerson(),
                'phone' => $client->getPhone(),
                'email' => $client->getEmail(),
            ];
        }, $clients);

        return $this->json([
            'success' => true,
            'data' => $data,
            'count' => \count($data),
        ]);
    }

    #[Route('/{id}', name: 'api_clients_show', methods: ['GET'])]
    public function show(int $id, ClientRepository $clientRepository): JsonResponse
    {
        $client = $clientRepository->find($id);

        if (!$client) {
            return $this->json([
                'success' => false,
                'error' => 'Client not found',
            ], 404);
        }

        $this->denyAccessUnlessGranted('view', $client);

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $client->getId(),
                'company_name' => $client->getCompanyName(),
                'inn' => $client->getInn(),
                'kpp' => $client->getKpp(),
                'contact_person' => $client->getContactPerson(),
                'phone' => $client->getPhone(),
                'email' => $client->getEmail(),
                'address' => $client->getAddress(),
                'segment' => $client->getSegment(),
                'category' => $client->getCategory(),
                'notes' => $client->getNotes(),
                'total_revenue' => (float) $client->getTotalRevenue(),
                'average_check' => (float) $client->getAverageCheck(),
                'completed_deals_count' => $client->getCompletedDealsCount(),
                'created_at' => $client->getCreatedAt()->format('Y-m-d H:i:s'),
                'last_contact_at' => $client->getLastContactAt()?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    #[Route('/{id}/stats', name: 'api_clients_stats', methods: ['GET'])]
    public function stats(int $id, ClientRepository $clientRepository): JsonResponse
    {
        $client = $clientRepository->find($id);

        if (!$client) {
            return $this->json([
                'success' => false,
                'error' => 'Client not found',
            ], 404);
        }

        $this->denyAccessUnlessGranted('view', $client);

        $deals = $client->getDeals();
        $wonDeals = $deals->filter(fn ($deal) => $deal->getStatus() === 'won');
        $lostDeals = $deals->filter(fn ($deal) => $deal->getStatus() === 'lost');

        return $this->json([
            'success' => true,
            'data' => [
                'total_revenue' => (float) $client->getTotalRevenue(),
                'average_check' => (float) $client->getAverageCheck(),
                'deals' => [
                    'total' => $deals->count(),
                    'won' => $wonDeals->count(),
                    'lost' => $lostDeals->count(),
                    'in_progress' => $deals->filter(fn ($deal) => $deal->getStatus() === 'in_progress')->count(),
                ],
            ],
        ]);
    }
}
