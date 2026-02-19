<?php

namespace App\Command;

use App\Service\TaskAutomationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:automate-tasks',
    description: 'Run task automation rules',
)]
class AutomateTasksCommand extends Command
{
    public function __construct(
        private TaskAutomationService $automationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('auto-assign', null, InputOption::VALUE_NONE, 'Auto-assign unassigned tasks')
            ->addOption('escalate', null, InputOption::VALUE_NONE, 'Escalate overdue tasks')
            ->addOption('archive', null, InputOption::VALUE_NONE, 'Archive old completed tasks')
            ->addOption('update-stale', null, InputOption::VALUE_NONE, 'Update stale task status')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Run all automations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Task Automation');

        $runAll = $input->getOption('all');

        // Auto-assign
        if ($runAll || $input->getOption('auto-assign')) {
            $io->section('Auto-assigning tasks');
            $assigned = $this->automationService->autoAssignTasks();
            $io->success(\sprintf('Assigned %d tasks', $assigned));
        }

        // Escalate overdue
        if ($runAll || $input->getOption('escalate')) {
            $io->section('Escalating overdue tasks');
            $escalated = $this->automationService->autoEscalateOverdueTasks();
            $io->success(\sprintf('Escalated %d tasks', $escalated));
        }

        // Archive old tasks
        if ($runAll || $input->getOption('archive')) {
            $io->section('Archiving old completed tasks');
            $archived = $this->automationService->autoArchiveOldTasks(90);
            $io->success(\sprintf('Archived %d tasks', $archived));
        }

        // Update stale tasks
        if ($runAll || $input->getOption('update-stale')) {
            $io->section('Updating stale tasks');
            $updated = $this->automationService->autoUpdateStaleTaskStatus();
            $io->success(\sprintf('Updated %d stale tasks', $updated));
        }

        // Execute custom rules
        if ($runAll) {
            $io->section('Executing automation rules');
            $executed = $this->automationService->executeRules();
            $io->success(\sprintf('Executed %d rules', $executed));
        }

        $io->success('Task automation completed');

        return Command::SUCCESS;
    }
}
