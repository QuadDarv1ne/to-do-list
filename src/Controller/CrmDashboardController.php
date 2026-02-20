<?php

namespace App\Controller;

use App\Repository\ClientRepository;
use App\Repository\DealRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/crm')]
#[IsGranted('ROLE_USER')]
class CrmDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_crm_dashboard', methods: ['GET'])]
    public function dashboard(
        ClientRepository $clientRepository,
        DealRepository $dealRepository,
        ProductRepository $productRepository
    ): Response {
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Статистика по клиентам
        $totalClients = $isAdmin 
            ? $clientRepository->getTotalCount()
            : $clientRepository->getTotalCount($user);

        $vipClients = $isAdmin
            ? $clientRepository->countByCategory('vip')
            : $clientRepository->countByCategory('vip', $user);

        // Статистика по сделкам
        $deals = $isAdmin
            ? $dealRepository->findAll()
            : $dealRepository->findByManager($user);

        $dealsStats = [
            'total' => count($deals),
            'in_progress' => count(array_filter($deals, fn($d) => $d->getStatus() === 'in_progress')),
            'won' => count(array_filter($deals, fn($d) => $d->getStatus() === 'won')),
            'lost' => count(array_filter($deals, fn($d) => $d->getStatus() === 'lost')),
        ];

        // Общая выручка
        $totalRevenue = array_reduce(
            array_filter($deals, fn($d) => $d->getStatus() === 'won'),
            fn($sum, $deal) => $sum + (float)$deal->getAmount(),
            0
        );

        // Средний чек
        $averageCheck = $dealsStats['won'] > 0 ? $totalRevenue / $dealsStats['won'] : 0;

        // Конверсия
        $closedDeals = $dealsStats['won'] + $dealsStats['lost'];
        $conversionRate = $closedDeals > 0 ? ($dealsStats['won'] / $closedDeals) * 100 : 0;

        // Сделки по этапам
        $dealsByStage = $dealRepository->getDealsByStage($isAdmin ? null : $user);

        // Просроченные сделки
        $overdueDeals = $dealRepository->getOverdueDeals($isAdmin ? null : $user);

        // Последние сделки
        $recentDeals = array_slice($deals, 0, 5);

        // Статистика по товарам
        $totalProducts = $productRepository->countAll();
        $activeProducts = $productRepository->countActive();

        return $this->render('crm/dashboard.html.twig', [
            'totalClients' => $totalClients,
            'vipClients' => $vipClients,
            'dealsStats' => $dealsStats,
            'totalRevenue' => $totalRevenue,
            'averageCheck' => $averageCheck,
            'conversionRate' => $conversionRate,
            'dealsByStage' => $dealsByStage,
            'overdueDeals' => $overdueDeals,
            'recentDeals' => $recentDeals,
            'totalProducts' => $totalProducts,
            'activeProducts' => $activeProducts,
        ]);
    }
}
