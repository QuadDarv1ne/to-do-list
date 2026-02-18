<?php

namespace App\Controller;

use App\Entity\Habit;
use App\Entity\HabitLog;
use App\Repository\HabitRepository;
use App\Repository\HabitLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/habits')]
#[IsGranted('ROLE_USER')]
class HabitController extends AbstractController
{
    #[Route('/', name: 'app_habits_index')]
    public function index(HabitRepository $habitRepository): Response
    {
        $user = $this->getUser();
        $habits = $habitRepository->findActiveByUser($user);
        
        return $this->render('habits/index.html.twig', [
            'habits' => $habits,
        ]);
    }

    #[Route('/create', name: 'app_habits_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $habit = new Habit();
        $habit->setName($data['name']);
        $habit->setDescription($data['description'] ?? null);
        $habit->setUser($this->getUser());
        $habit->setFrequency($data['frequency'] ?? 'daily');
        $habit->setWeekDays($data['weekDays'] ?? []);
        $habit->setTargetCount($data['targetCount'] ?? 1);
        $habit->setCategory($data['category'] ?? 'health');
        $habit->setIcon($data['icon'] ?? 'fa-check');
        $habit->setColor($data['color'] ?? '#667eea');
        
        $em->persist($habit);
        $em->flush();
        
        return $this->json([
            'success' => true,
            'habit' => [
                'id' => $habit->getId(),
                'name' => $habit->getName(),
            ]
        ]);
    }

    #[Route('/{id}/log', name: 'app_habits_log', methods: ['POST'])]
    public function logHabit(
        Habit $habit,
        Request $request,
        EntityManagerInterface $em,
        HabitLogRepository $logRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $date = new \DateTime($data['date'] ?? 'today');
        $date->setTime(0, 0, 0);
        
        $log = $logRepository->findByHabitAndDate($habit, $date);
        
        if (!$log) {
            $log = new HabitLog();
            $log->setHabit($habit);
            $log->setDate($date);
            $log->setCount($data['count'] ?? 1);
            $log->setNote($data['note'] ?? null);
            $em->persist($log);
        } else {
            $log->setCount($log->getCount() + ($data['count'] ?? 1));
        }
        
        $em->flush();
        
        return $this->json([
            'success' => true,
            'streak' => $habit->getCurrentStreak(),
            'count' => $log->getCount()
        ]);
    }

    #[Route('/{id}/unlog', name: 'app_habits_unlog', methods: ['POST'])]
    public function unlogHabit(
        Habit $habit,
        Request $request,
        EntityManagerInterface $em,
        HabitLogRepository $logRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $date = new \DateTime($data['date'] ?? 'today');
        $date->setTime(0, 0, 0);
        
        $log = $logRepository->findByHabitAndDate($habit, $date);
        
        if ($log) {
            $em->remove($log);
            $em->flush();
        }
        
        return $this->json([
            'success' => true,
            'streak' => $habit->getCurrentStreak()
        ]);
    }

    #[Route('/{id}/stats', name: 'app_habits_stats', methods: ['GET'])]
    public function getStats(Habit $habit, HabitLogRepository $logRepository): JsonResponse
    {
        $endDate = new \DateTime();
        $startDate = (clone $endDate)->modify('-30 days');
        
        $logs = $logRepository->findByHabitAndDateRange($habit, $startDate, $endDate);
        
        $heatmapData = [];
        foreach ($logs as $log) {
            $heatmapData[$log->getDate()->format('Y-m-d')] = $log->getCount();
        }
        
        return $this->json([
            'currentStreak' => $habit->getCurrentStreak(),
            'longestStreak' => $habit->getLongestStreak(),
            'completionRate' => $habit->getCompletionRate(30),
            'totalLogs' => count($habit->getLogs()),
            'heatmapData' => $heatmapData
        ]);
    }

    #[Route('/{id}/delete', name: 'app_habits_delete', methods: ['DELETE'])]
    public function delete(Habit $habit, EntityManagerInterface $em): JsonResponse
    {
        $habit->setActive(false);
        $em->flush();
        
        return $this->json(['success' => true]);
    }
}
