<?php

namespace App\Controller;

use App\Entity\Habit;
use App\Entity\HabitLog;
use App\Repository\HabitLogRepository;
use App\Repository\HabitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/habits')]
#[IsGranted('ROLE_USER')]
class HabitController extends AbstractController
{
    public function __construct(
        private HabitRepository $habitRepository,
        private HabitLogRepository $logRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/', name: 'app_habits_index', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        $habits = $this->habitRepository->findActiveByUser($user);

        return $this->render('habits/index.html.twig', [
            'habits' => $habits,
        ]);
    }

    #[Route('/{id}', name: 'app_habits_show', methods: ['GET'])]
    public function show(Habit $habit): Response
    {
        $this->denyAccessUnlessGranted('view', $habit);

        // Получаем статистику из репозитория
        $stats = $this->getHabitStats($habit);

        return $this->render('habits/show.html.twig', [
            'habit' => $habit,
            'currentStreak' => $stats['currentStreak'],
            'totalCompletions' => $stats['totalCompletions'],
            'successRate' => $stats['successRate'],
            'bestStreak' => $stats['bestStreak'],
            'completionRate' => $stats['completionRate'],
            'last30Days' => $stats['last30Days'],
            'logs' => $stats['logs'],
        ]);
    }

    #[Route('/{id}/log', name: 'app_habits_log', methods: ['POST'])]
    public function log(Request $request, Habit $habit): Response
    {
        $this->denyAccessUnlessGranted('edit', $habit);

        $status = $request->request->get('status', 'completed');
        $note = $request->request->get('note');
        $count = (int) $request->request->get('count', 1);
        $date = new \DateTime();

        if ($status === 'completed') {
            $this->logRepository->logCompletion($habit, $date, $count, $note);
            $this->addFlash('success', 'Привычка выполнена! Отличная работа! 🎉');
        }

        return $this->redirectToRoute('app_habits_show', ['id' => $habit->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_habits_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Habit $habit): Response
    {
        $this->denyAccessUnlessGranted('edit', $habit);

        if ($request->isMethod('POST')) {
            $habit->setName($request->request->get('name'));
            $habit->setDescription($request->request->get('description'));
            $habit->setFrequency($request->request->get('frequency', 'daily'));
            $habit->setTargetCount((int) $request->request->get('target_count', 1));
            $habit->setCategory($request->request->get('category', 'health'));
            $habit->setIcon($request->request->get('icon', 'fa-check'));
            $habit->setColor($request->request->get('color', '#667eea'));

            $this->entityManager->flush();
            $this->addFlash('success', 'Привычка обновлена!');

            return $this->redirectToRoute('app_habits_show', ['id' => $habit->getId()]);
        }

        return $this->render('habits/edit.html.twig', [
            'habit' => $habit,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_habits_delete', methods: ['POST'])]
    public function delete(Request $request, Habit $habit): Response
    {
        $this->denyAccessUnlessGranted('delete', $habit);

        if ($this->isCsrfTokenValid('delete' . $habit->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($habit);
            $this->entityManager->flush();
            $this->addFlash('success', 'Привычка удалена');
        }

        return $this->redirectToRoute('app_habits_index');
    }

    private function getHabitStats(Habit $habit): array
    {
        // Получаем логи за последние 30 дней
        $last30DaysRaw = $this->logRepository->findLastDays($habit, 30);
        
        // Генерируем последние 30 дней с отметками о выполнении
        $last30Days = [];
        $logsByDate = [];
        
        foreach ($last30DaysRaw as $log) {
            $logsByDate[$log->getDate()->format('Y-m-d')] = $log;
        }
        
        for ($i = 29; $i >= 0; $i--) {
            $date = (new \DateTime())->modify("-$i days");
            $dateKey = $date->format('Y-m-d');
            
            $last30Days[] = [
                'date' => $date,
                'completed' => isset($logsByDate[$dateKey]),
                'missed' => !isset($logsByDate[$dateKey]) && $i > 0,
                'isToday' => $i === 0,
            ];
        }

        // Получаем общую статистику
        $stats = $this->logRepository->getHabitStats($habit);
        
        // Получаем серию
        $currentStreak = $this->logRepository->getCurrentStreak($habit);
        $bestStreak = $this->logRepository->getLongestStreak($habit);
        
        // Получаем процент выполнения
        $completionRate = round($this->logRepository->getCompletionRate($habit, 30));
        
        // Рассчитываем успешность
        $totalDays = 30;
        $completedDays = count($last30DaysRaw);
        $successRate = $totalDays > 0 ? round(($completedDays / $totalDays) * 100) : 0;

        // Получаем последние логи
        $logs = array_slice($last30DaysRaw, 0, 20);

        return [
            'currentStreak' => $currentStreak,
            'totalCompletions' => $stats['totalCompletions'],
            'successRate' => $successRate,
            'bestStreak' => $bestStreak,
            'completionRate' => $completionRate,
            'last30Days' => $last30Days,
            'logs' => $logs,
        ];
    }
}
