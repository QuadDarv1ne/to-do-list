<?php
// src/Controller/DashboardController.php

namespace App\Controller;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(TaskRepository $taskRepository, UserRepository $userRepository): Response
    {
        $user = $this->getUser();
        
        // Получаем статистику для текущего пользователя
        $totalTasks = $taskRepository->count(['id' => null]); // общее количество задач
        $completedTasks = $taskRepository->count(['isDone' => true]);
        $pendingTasks = $totalTasks - $completedTasks;
        
        // Получаем последние задачи
        $recentTasks = $taskRepository->findBy([], ['createdAt' => 'DESC'], 5);
        
        // Для администраторов показываем дополнительную информацию
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $userStats = null;
        
        if ($isAdmin) {
            $userStats = $userRepository->getStatistics();
        }
        
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'pending_tasks' => $pendingTasks,
            'recent_tasks' => $recentTasks,
            'user_stats' => $userStats,
        ]);
    }
}