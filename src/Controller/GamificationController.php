<?php

namespace App\Controller;

use App\Service\GamificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/gamification')]
class GamificationController extends AbstractController
{
    public function __construct(
        private GamificationService $gamificationService,
    ) {
    }

    #[Route('', name: 'app_gamification_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $level = $this->gamificationService->getUserLevel($user);
        $achievements = $this->gamificationService->getUserAchievements($user);
        $streak = $this->gamificationService->getUserStreak($user);
        $rank = $this->gamificationService->getUserRank($user);
        $dailyChallenge = $this->gamificationService->getDailyChallenge($user);

        return $this->render('gamification/index.html.twig', [
            'level' => $level,
            'achievements' => $achievements,
            'streak' => $streak,
            'rank' => $rank,
            'daily_challenge' => $dailyChallenge,
        ]);
    }

    #[Route('/api/level', name: 'app_gamification_api_level')]
    public function apiLevel(): JsonResponse
    {
        $user = $this->getUser();
        $level = $this->gamificationService->getUserLevel($user);

        return $this->json($level);
    }

    #[Route('/api/achievements', name: 'app_gamification_api_achievements')]
    public function apiAchievements(): JsonResponse
    {
        $user = $this->getUser();
        $achievements = $this->gamificationService->getUserAchievements($user);

        return $this->json($achievements);
    }

    #[Route('/api/leaderboard', name: 'app_gamification_api_leaderboard')]
    public function apiLeaderboard(): JsonResponse
    {
        $leaderboard = $this->gamificationService->getLeaderboard(10);

        return $this->json($leaderboard);
    }

    #[Route('/api/streak', name: 'app_gamification_api_streak')]
    public function apiStreak(): JsonResponse
    {
        $user = $this->getUser();
        $streak = $this->gamificationService->getUserStreak($user);

        return $this->json($streak);
    }

    #[Route('/api/daily-challenge', name: 'app_gamification_api_daily_challenge')]
    public function apiDailyChallenge(): JsonResponse
    {
        $user = $this->getUser();
        $challenge = $this->gamificationService->getDailyChallenge($user);

        return $this->json($challenge);
    }

    #[Route('/shop', name: 'app_gamification_shop')]
    public function shop(): Response
    {
        $items = $this->gamificationService->getShopItems();
        $user = $this->getUser();
        $level = $this->gamificationService->getUserLevel($user);

        return $this->render('gamification/shop.html.twig', [
            'items' => $items,
            'user_xp' => $level['xp'],
        ]);
    }
}
