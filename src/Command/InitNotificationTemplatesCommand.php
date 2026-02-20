<?php

namespace App\Command;

use App\Service\NotificationTemplateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notifications:init-templates',
    description: 'Initialize default notification templates',
)]
class InitNotificationTemplatesCommand extends Command
{
    public function __construct(
        private NotificationTemplateService $templateService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('This command creates default notification templates in the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Initializing Notification Templates');
        
        try {
            $this->templateService->createDefaultTemplates();
            $io->success('Notification templates have been successfully initialized!');
            
            // Show created templates
            $templates = [
                'task_assigned' => 'Task Assigned (Email)',
                'task_completed' => 'Task Completed (Email)', 
                'deadline_reminder' => 'Deadline Reminder (Email)',
                'system_alert' => 'System Alert (Email)',
            ];
            
            $io->section('Created templates:');
            foreach ($templates as $key => $name) {
                $io->writeln("• {$name} ({$key})");
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to initialize templates: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}