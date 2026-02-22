<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Добавление индексов для оптимизации производительности
 */
final class Version20260222100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Добавление индексов на часто используемые поля для оптимизации запросов';
    }

    public function up(Schema $schema): void
    {
        // Индексы для таблицы tasks
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_user ON tasks (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_assigned_user ON tasks (assigned_user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_status ON tasks (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_priority ON tasks (priority)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_created_at ON tasks (created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_due_date ON tasks (due_date)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_user_status ON tasks (user_id, status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_user_priority ON tasks (user_id, priority)');
        
        // Индексы для таблицы comments
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_comment_task ON comments (task_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_comment_author ON comments (author_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_comment_created_at ON comments (created_at)');
        
        // Индексы для таблицы task_history
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_history_task ON task_history (task_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_history_user ON task_history (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task_history_created_at ON task_history (created_at)');
        
        // Индексы для таблицы activity_log
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_log_user ON activity_log (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_activity_log_created_at ON activity_log (created_at)');
        
        // Индексы для таблицы notifications
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notification_user ON notifications (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notification_is_read ON notifications (is_read)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_notification_created_at ON notifications (created_at)');
        
        // Индексы для таблицы clients
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_client_manager ON clients (manager_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_client_company_name ON clients (company_name)');
        
        // Индексы для таблицы deals
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_deal_client ON deals (client_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_deal_manager ON deals (manager_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_deal_status ON deals (status)');
    }

    public function down(Schema $schema): void
    {
        // Удаление индексов при откате
        $this->addSql('DROP INDEX IF EXISTS idx_task_user');
        $this->addSql('DROP INDEX IF EXISTS idx_task_assigned_user');
        $this->addSql('DROP INDEX IF EXISTS idx_task_status');
        $this->addSql('DROP INDEX IF EXISTS idx_task_priority');
        $this->addSql('DROP INDEX IF EXISTS idx_task_created_at');
        $this->addSql('DROP INDEX IF EXISTS idx_task_due_date');
        $this->addSql('DROP INDEX IF EXISTS idx_task_user_status');
        $this->addSql('DROP INDEX IF EXISTS idx_task_user_priority');
        
        $this->addSql('DROP INDEX IF EXISTS idx_comment_task');
        $this->addSql('DROP INDEX IF EXISTS idx_comment_author');
        $this->addSql('DROP INDEX IF EXISTS idx_comment_created_at');
        
        $this->addSql('DROP INDEX IF EXISTS idx_task_history_task');
        $this->addSql('DROP INDEX IF EXISTS idx_task_history_user');
        $this->addSql('DROP INDEX IF EXISTS idx_task_history_created_at');
        
        $this->addSql('DROP INDEX IF EXISTS idx_activity_log_user');
        $this->addSql('DROP INDEX IF EXISTS idx_activity_log_created_at');
        
        $this->addSql('DROP INDEX IF EXISTS idx_notification_user');
        $this->addSql('DROP INDEX IF EXISTS idx_notification_is_read');
        $this->addSql('DROP INDEX IF EXISTS idx_notification_created_at');
        
        $this->addSql('DROP INDEX IF EXISTS idx_client_manager');
        $this->addSql('DROP INDEX IF EXISTS idx_client_company_name');
        
        $this->addSql('DROP INDEX IF EXISTS idx_deal_client');
        $this->addSql('DROP INDEX IF EXISTS idx_deal_manager');
        $this->addSql('DROP INDEX IF EXISTS idx_deal_status');
    }
}
