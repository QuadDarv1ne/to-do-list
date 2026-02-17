<?php

namespace App\Command;

use App\Service\RecurringTaskService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-recurring-tasks',
    description: 'Process recurring tasks and create new instances'
)]
class ProcessRecurringTasksCommand extends Command
{
    public function __construct(
        private RecurringTaskService $recurringTaskService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Processing Recurring Tasks');

        $created = $this->recurringTaskService->processRecurringTasks();

        if (empty($created)) {
            $io->success('No recurring tasks to process');
            return Command::SUCCESS;
        }

        $io->success(sprintf('Created %d recurring tasks', count($created)));

        foreach ($created as $task) {
            $io->writeln(sprintf('  - Task #%d: %s', $task->getId(), $task->getTitle()));
        }

        return Command::SUCCESS;
    }
}
