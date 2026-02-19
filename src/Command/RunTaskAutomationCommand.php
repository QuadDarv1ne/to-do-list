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
    name: 'app:run-task-automation',
    description: 'Run task automation rules',
)]
class RunTaskAutomationCommand extends Command
{
    public function __construct(
        private TaskAutomationService $automationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('rule', 'r', InputOption::VALUE_OPTIONAL, 'Specific rule to run')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rule = $input->getOption('rule');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->warning('Running in DRY RUN mode - no changes will be made');
        }

        $io->title('Task Automation');

        if ($rule) {
            $io->section("Running rule: {$rule}");
            $result = $this->runSpecificRule($rule);
            $io->success("Processed {$result} tasks");
        } else {
            $io->section('Running all automation rules');
            $results = $this->automationService->runAllAutomations();

            $io->table(
                ['Rule', 'Tasks Processed'],
                [
                    ['Auto-assign', $results['auto_assigned']],
                    ['Auto-close', $results['auto_closed']],
                    ['Auto-escalate', $results['auto_escalated']],
                    ['Auto-tag', $results['auto_tagged']],
                    ['Auto-status', $results['auto_status_updated']],
                ],
            );

            $total = array_sum($results);
            $io->success("Total tasks processed: {$total}");
        }

        return Command::SUCCESS;
    }

    private function runSpecificRule(string $rule): int
    {
        return match($rule) {
            'assign' => $this->automationService->autoAssignTasks(),
            'close' => $this->automationService->autoCloseCompletedTasks(),
            'escalate' => $this->automationService->autoEscalateOverdueTasks(),
            'tag' => $this->automationService->autoTagTasks(),
            'status' => $this->automationService->autoUpdateStatus(),
            default => 0
        };
    }
}
