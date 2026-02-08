<?php

namespace App\Command;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-deadline-notifications',
    description: 'Send notifications for tasks with approaching deadlines',
)]
class SendDeadlineNotificationsCommand extends Command
{
    public function __construct(
        private TaskRepository $taskRepository,
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find tasks with deadlines in the next 2 days that are not completed
        $upcomingDeadlineTasks = $this->taskRepository->findUpcomingDeadlines(
            new \DateTimeImmutable('+2 days')
        );

        $count = 0;
        foreach ($upcomingDeadlineTasks as $task) {
            // Skip if task is already completed
            if ($task->isCompleted()) {
                continue;
            }

            $this->notificationService->notifyTaskDeadline($task);
            $count++;
        }

        $io->success(sprintf('Sent %d deadline reminder notifications.', $count));

        return Command::SUCCESS;
    }
}