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

        // Статистика по сделкам - оптимизировано через DQL
        $dealsStats = $dealRepository->getDealsStatsByStatus($isAdmin ? null : $user);
        
        // Общая выручка
        $totalRevenue = $dealRepository->getTotalRevenue($isAdmin ? null : $user);

        // Средний чек
        $averageCheck = $dealsStats['won'] > 0 ? $totalRevenue / $dealsStats['won'] : 0;

        // Конверсия
        $closedDeals = $dealsStats['won'] + $dealsStats['lost'];
        $conversionRate = $closedDeals > 0 ? ($dealsStats['won'] / $closedDeals) * 100 : 0;

        // Сделки по этапам
        $dealsByStage = $dealRepository->getDealsByStage($isAdmin ? null : $user);

        // Просроченные сделки
        $overdueDeals = $dealRepository->getOverdueDeals($isAdmin ? null : $user);

        // Последние сделки - оптимизировано с JOIN
        $recentDeals = $isAdmin
            ? $dealRepository->findAllWithRelations()
            : $dealRepository->findByManager($user);
        $recentDeals = array_slice($recentDeals, 0, 5);

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
