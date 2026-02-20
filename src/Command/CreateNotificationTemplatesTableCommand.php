<?php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:database:create-notification-templates',
    description: 'Create notification templates table manually',
)]
class CreateNotificationTemplatesTableCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $io->title('Creating Notification Templates Table');
        
        try {
            $connection = $this->entityManager->getConnection();
            
            // Create table
            $sql = "CREATE TABLE IF NOT EXISTS notification_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                key VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                subject TEXT NOT NULL,
                content TEXT NOT NULL,
                channel VARCHAR(50) NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                variables TEXT,
                created_at DATETIME NOT NULL,
                updated_at DATETIME
            )";
            
            $connection->executeQuery($sql);
            
            // Create indexes
            $indexes = [
                "CREATE UNIQUE INDEX IF NOT EXISTS UNIQ_C9C13AD18A90ABA9 ON notification_templates (key)",
                "CREATE INDEX IF NOT EXISTS idx_key_active ON notification_templates (key, is_active)",
                "CREATE INDEX IF NOT EXISTS idx_channel ON notification_templates (channel)"
            ];
            
            foreach ($indexes as $indexSql) {
                $connection->executeQuery($indexSql);
            }
            
            $io->success('Notification templates table created successfully!');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('Failed to create table: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}