<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Clean up redundant tables and optimize for MySQL/PostgreSQL compatibility
 */
final class Version20260212080648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clean up redundant tables and optimize database structure for MySQL/PostgreSQL compatibility';
    }

    public function up(Schema $schema): void
    {
        // This migration consolidates and optimizes the database structure
        // First, drop redundant indexes that may cause issues with PostgreSQL
        $this->addSql('DROP INDEX IF EXISTS UNIQ_IDENTIFIER_USERNAME');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_1483A5E9E7927C74');
        
        // Recreate unique indexes with proper naming for cross-platform compatibility
        $this->addSql('CREATE UNIQUE INDEX users_username_unique_idx ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX users_email_unique_idx ON users (email)');
        
        // Add optimized indexes for better query performance
        $this->addSql('CREATE INDEX tasks_status_idx ON tasks (status)');
        $this->addSql('CREATE INDEX tasks_priority_idx ON tasks (priority)');
        $this->addSql('CREATE INDEX tasks_due_date_idx ON tasks (due_date)');
        $this->addSql('CREATE INDEX tasks_created_at_idx ON tasks (created_at)');
        $this->addSql('CREATE INDEX tasks_assigned_user_idx ON tasks (assigned_user_id)');
        $this->addSql('CREATE INDEX activity_logs_user_idx ON activity_logs (user_id)');
        $this->addSql('CREATE INDEX activity_logs_task_idx ON activity_logs (task_id)');
        $this->addSql('CREATE INDEX activity_logs_created_at_idx ON activity_logs (created_at)');
        $this->addSql('CREATE INDEX task_comments_task_idx ON task_comments (task_id)');
        $this->addSql('CREATE INDEX task_comments_author_idx ON task_comments (author_id)');
        $this->addSql('CREATE INDEX task_comments_created_at_idx ON task_comments (created_at)');
        $this->addSql('CREATE INDEX task_notifications_recipient_idx ON task_notifications (recipient_id)');
        $this->addSql('CREATE INDEX task_notifications_task_idx ON task_notifications (task_id)');
        $this->addSql('CREATE INDEX task_time_tracking_user_idx ON task_time_tracking (user_id)');
        $this->addSql('CREATE INDEX task_time_tracking_task_idx ON task_time_tracking (task_id)');
        $this->addSql('CREATE INDEX task_categories_user_idx ON task_categories (user_id)');
        
        // Add composite indexes for common query patterns
        $this->addSql('CREATE INDEX tasks_user_status_idx ON tasks (user_id, status)');
        $this->addSql('CREATE INDEX tasks_assigned_status_idx ON tasks (assigned_user_id, status)');
        $this->addSql('CREATE INDEX tasks_user_priority_idx ON tasks (user_id, priority)');
        $this->addSql('CREATE INDEX notifications_user_read_idx ON notifications (user_id, is_read)');
    }

    public function down(Schema $schema): void
    {
        // Drop the indexes we created
        $this->addSql('DROP INDEX IF EXISTS users_username_unique_idx');
        $this->addSql('DROP INDEX IF EXISTS users_email_unique_idx');
        $this->addSql('DROP INDEX IF EXISTS tasks_status_idx');
        $this->addSql('DROP INDEX IF EXISTS tasks_priority_idx');
        $this->addSql('DROP INDEX IF EXISTS tasks_due_date_idx');
        $this->addSql('DROP INDEX IF EXISTS tasks_created_at_idx');
        $this->addSql('DROP INDEX IF EXISTS tasks_assigned_user_idx');
        $this->addSql('DROP INDEX IF EXISTS activity_logs_user_idx');
        $this->addSql('DROP INDEX IF EXISTS activity_logs_task_idx');
        $this->addSql('DROP INDEX IF EXISTS activity_logs_created_at_idx');
        $this->addSql('DROP INDEX IF EXISTS task_comments_task_idx');
        $this->addSql('DROP INDEX IF EXISTS task_comments_author_idx');
        $this->addSql('DROP INDEX IF EXISTS task_comments_created_at_idx');
        $this->addSql('DROP INDEX IF EXISTS task_notifications_recipient_idx');
        $this->addSql('DROP INDEX IF EXISTS task_notifications_task_idx');
        $this->addSql('DROP INDEX IF EXISTS task_time_tracking_user_idx');
        $this->addSql('DROP INDEX IF EXISTS task_time_tracking_task_idx');
        $this->addSql('DROP INDEX IF EXISTS task_categories_user_idx');
        $this->addSql('DROP INDEX IF EXISTS tasks_user_status_idx');
        $this->addSql('DROP INDEX IF EXISTS tasks_assigned_status_idx');
        $this->addSql('DROP INDEX IF EXISTS tasks_user_priority_idx');
        $this->addSql('DROP INDEX IF EXISTS notifications_user_read_idx');
        
        // Restore original unique indexes
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
    }
}