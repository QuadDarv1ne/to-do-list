<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217095615 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes to optimize task search and filtering operations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        
        // Add additional performance indexes for improved query performance
        $this->addSql('CREATE INDEX idx_tasks_title ON tasks (title)');
        $this->addSql('CREATE INDEX idx_tasks_description ON tasks (description)');
        // For full-text search in SQLite, we'll create an index on title and description separately
        $this->addSql('CREATE INDEX idx_tasks_user_assigned_status ON tasks (user_id, assigned_user_id, status)'); // For complex user queries
        $this->addSql('CREATE INDEX idx_tasks_priority_due_date ON tasks (priority, due_date)'); // For priority sorting with deadlines
        
        // Indexes for tags table
        $this->addSql('CREATE INDEX idx_tags_name ON tags (name)');
        $this->addSql('CREATE INDEX idx_tags_user_created ON tags (user_id, created_at)');
        
        // Indexes for task_tags junction table (if exists)
        $this->addSql('CREATE INDEX idx_task_tags_task_id ON task_tags (task_id)');
        $this->addSql('CREATE INDEX idx_task_tags_tag_id ON task_tags (tag_id)');
        
        // Additional indexes for performance optimization
        $this->addSql('CREATE INDEX idx_activity_logs_created_user ON activity_logs (created_at, user_id)');
        $this->addSql('CREATE INDEX idx_comments_task_created ON task_comments (task_id, created_at)');
        $this->addSql('CREATE INDEX idx_time_tracking_task_user ON task_time_tracking (task_id, user_id)');
        
        // Composite index for advanced search scenarios
        $this->addSql('CREATE INDEX idx_tasks_complex_search ON tasks (user_id, status, priority, due_date)');
    }

    public function down(Schema $schema): void
    {
        // Drop the performance indexes added in up() method
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_title');
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_description');
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_title_description_fulltext');
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_user_assigned_status');
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_priority_due_date');
        $this->addSql('DROP INDEX IF EXISTS idx_tags_name');
        $this->addSql('DROP INDEX IF EXISTS idx_tags_user_created');
        $this->addSql('DROP INDEX IF EXISTS idx_task_tags_task_id');
        $this->addSql('DROP INDEX IF EXISTS idx_task_tags_tag_id');
        $this->addSql('DROP INDEX IF EXISTS idx_activity_logs_created_user');
        $this->addSql('DROP INDEX IF EXISTS idx_comments_task_created');
        $this->addSql('DROP INDEX IF EXISTS idx_time_tracking_task_user');
        $this->addSql('DROP INDEX IF EXISTS idx_tasks_complex_search');
    }
}
