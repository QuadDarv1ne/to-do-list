<?php

namespace App\Command;

use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-old-tasks',
    description: 'Clean up completed tasks older than 1 year',
)]
class CleanupOldTasksCommand extends Command
{
    public function __construct(
        private TaskRepository $taskRepository,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find completed tasks older than 1 year
        $oneYearAgo = new \DateTime('-1 year');
        $oldCompletedTasks = $this->taskRepository->findCompletedTasksOlderThan($oneYearAgo);

        $count = \count($oldCompletedTasks);

        if ($count === 0) {
            $io->success('No old completed tasks found for cleanup.');

            return Command::SUCCESS;
        }

        // Remove old tasks
        foreach ($oldCompletedTasks as $task) {
            $this->entityManager->remove($task);
        }

        $this->entityManager->flush();

        $io->success(\sprintf('Removed %d old completed tasks.', $count));

        return Command::SUCCESS;
    }
}
