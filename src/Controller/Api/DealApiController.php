<?php

namespace App\Controller\Api;

use App\Repository\DealRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/deals')]
#[IsGranted('ROLE_USER')]
class DealApiController extends AbstractController
{
    #[Route('', name: 'api_deals_list', methods: ['GET'])]
    public function list(DealRepository $dealRepository, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $status = $request->query->get('status');
        
        $deals = $this->isGranted('ROLE_ADMIN')
            ? $dealRepository->findAll()
            : $dealRepository->findByManager($user);

        if ($status) {
            $deals = array_filter($deals, fn($deal) => $deal->getStatus() === $status);
        }

        $data = array_map(function($deal) {
            return [
                'id' => $deal->getId(),
                'title' => $deal->getTitle(),
                'client' => [
                    'id' => $deal->getClient()->getId(),
                    'name' => $deal->getClient()->getCompanyName(),
                ],
                'amount' => (float) $deal->getAmount(),
                'stage' => $deal->getStage(),
                'status' => $deal->getStatus(),
                'created_at' => $deal->getCreatedAt()->format('Y-m-d H:i:s'),
                'expected_close_date' => $deal->getExpectedCloseDate()?->format('Y-m-d'),
                'is_overdue' => $deal->isOverdue(),
            ];
        }, $deals);

        return $this->json([
            'success' => true,
            'data' => array_values($data),
            'count' => count($data),
        ]);
    }

    #[Route('/stats', name: 'api_deals_stats', methods: ['GET'])]
    public function stats(DealRepository $dealRepository): JsonResponse
    {
        $user = $this->getUser();
        $manager = $this->isGranted('ROLE_ADMIN') ? null : $user;

        $dealsByStage = $dealRepository->getDealsByStage($manager);
        $dealsCountByStatus = $dealRepository->getDealsCountByStatus($manager);

        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month');
        $monthRevenue = $dealRepository->getTotalRevenue($startOfMonth, $endOfMonth, $manager);

        return $this->json([
            'success' => true,
            'data' => [
                'by_stage' => $dealsByStage,
                'by_status' => $dealsCountByStatus,
                'month_revenue' => (float) $monthRevenue,
            ],
        ]);
    }

    #[Route('/{id}', name: 'api_deals_show', methods: ['GET'])]
    public function show(int $id, DealRepository $dealRepository): JsonResponse
    {
        $deal = $dealRepository->find($id);

        if (!$deal) {
            return $this->json([
                'success' => false,
                'error' => 'Deal not found',
            ], 404);
        }

        $this->denyAccessUnlessGranted('view', $deal);

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $deal->getId(),
                'title' => $deal->getTitle(),
                'client' => [
                    'id' => $deal->getClient()->getId(),
                    'name' => $deal->getClient()->getCompanyName(),
                ],
                'manager' => [
                    'id' => $deal->getManager()->getId(),
                    'name' => $deal->getManager()->getFullName(),
                ],
                'amount' => (float) $deal->getAmount(),
                'stage' => $deal->getStage(),
                'status' => $deal->getStatus(),
                'description' => $deal->getDescription(),
                'created_at' => $deal->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $deal->getUpdatedAt()?->format('Y-m-d H:i:s'),
                'expected_close_date' => $deal->getExpectedCloseDate()?->format('Y-m-d'),
                'actual_close_date' => $deal->getActualCloseDate()?->format('Y-m-d'),
                'is_overdue' => $deal->isOverdue(),
                'days_until_close' => $deal->getDaysUntilClose(),
            ],
        ]);
    }
}
