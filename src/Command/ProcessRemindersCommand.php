<?php

namespace App\Command;

use App\Repository\TaskRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-reminders',
    description: 'Обработать напоминания о задачах'
)]
class ProcessRemindersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService,
        private TaskRepository $taskRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Обработка напоминаний о задачах');

        // 1. Отправить напоминания
        $io->section('Отправка напоминаний...');
        $reminderResult = $this->notificationService->processTaskReminders();
        $io->success(sprintf('Отправлено: %d, ошибок: %d', $reminderResult['sent'], $reminderResult['failed']));

        // 2. Найти просроченные задачи
        $io->section('Проверка просроченных задач...');
        $overdueTasks = $this->taskRepository->createQueryBuilder('t')
            ->andWhere('t.dueDate < :now')
            ->andWhere('t.status != :completed')
            ->andWhere('t.overdueNotificationSent = :sent')
            ->setParameter('now', new \DateTime())
            ->setParameter('completed', 'completed')
            ->setParameter('sent', false)
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $sent = 0;
        foreach ($overdueTasks as $task) {
            try {
                $this->notificationService->sendOverdueNotification($task);
                $task->setOverdueNotificationSent(true);
                $this->entityManager->flush($task);
                $sent++;
            } catch (\Exception $e) {
                $io->warning('Ошибка отправки для задачи ' . $task->getId() . ': ' . $e->getMessage());
            }
        }

        $io->success("Уведомлений о просроченных задачах отправлено: {$sent}");

        // 3. Агрегировать статистику
        $stats = $this->getStats();
        $io->section('Статистика');
        $io->table(
            ['Метрика', 'Значение'],
            [
                ['Всего задач', $stats['total_tasks']],
                ['Активных', $stats['active_tasks']],
                ['Просроченных', $stats['overdue_tasks']],
                ['Завершённых', $stats['completed_tasks']],
            ]
        );

        return Command::SUCCESS;
    }

    private function getStats(): array
    {
        $qb = $this->taskRepository->createQueryBuilder('t');

        return [
            'total_tasks' => (clone $qb)->select('COUNT(t)')->getQuery()->getSingleScalarResult(),
            'active_tasks' => (clone $qb)->select('COUNT(t)')
                ->andWhere('t.status != :completed')
                ->setParameter('completed', 'completed')
                ->getQuery()->getSingleScalarResult(),
            'overdue_tasks' => (clone $qb)->select('COUNT(t)')
                ->andWhere('t.dueDate < :now')
                ->andWhere('t.status != :completed')
                ->setParameter('now', new \DateTime())
                ->setParameter('completed', 'completed')
                ->getQuery()->getSingleScalarResult(),
            'completed_tasks' => (clone $qb)->select('COUNT(t)')
                ->andWhere('t.status = :completed')
                ->setParameter('completed', 'completed')
                ->getQuery()->getSingleScalarResult(),
        ];
    }
}
