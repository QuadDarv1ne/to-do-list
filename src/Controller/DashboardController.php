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
        if ($this->isGranted('ROLE_ADMIN')) {
            // Администратор видит общую статистику
            $totalTasks = $taskRepository->count([]);
            $completedTasks = $taskRepository->count(['isDone' => true]);
            $pendingTasks = $taskRepository->count(['isDone' => false]);
            
            // Статистика по пользователям
            $userStats = $userRepository->getStatistics();
            
            // Получаем задачи всех пользователей
            $recentTasks = $taskRepository->findBy([], ['createdAt' => 'DESC'], 5);
            
            // Статистика по статусам для диаграммы
            $taskStats = [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'pending' => $pendingTasks,
            ];
        } else {
            // Обычный пользователь видит только свою статистику
            $totalTasks = $taskRepository->countByStatus($user);
            $completedTasks = $taskRepository->countByStatus($user, true);
            $pendingTasks = $taskRepository->countByStatus($user, false);
            
            // Получаем последние задачи пользователя
            $recentTasks = $taskRepository->findBy(['assignedUser' => $user], ['createdAt' => 'DESC'], 5);
            
            $userStats = null;
            
            // Статистика по статусам для диаграммы
            $taskStats = [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'pending' => $pendingTasks,
            ];
        }
        
        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'task_stats' => $taskStats,
            'recent_tasks' => $recentTasks,
            'user_stats' => $userStats,
        ]);
    }
}