<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260214100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes to optimize database queries';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_task_completed_at ON tasks (completed_at)');
        $this->addSql('CREATE INDEX idx_task_updated_at ON tasks (updated_at)');
        $this->addSql('CREATE INDEX idx_task_created_at_status ON tasks (created_at, status)');
        $this->addSql('CREATE INDEX idx_task_user_status ON tasks (user_id, status)');
        $this->addSql('CREATE INDEX idx_task_user_priority ON tasks (user_id, priority)');
        $this->addSql('CREATE INDEX idx_task_assigned_user_status ON tasks (assigned_user_id, status)');
        $this->addSql('CREATE INDEX idx_task_category_status ON tasks (category_id, status)');
        $this->addSql('CREATE INDEX idx_task_due_date_status ON tasks (due_date, status)');
        
        // Indexes for comments table
        $this->addSql('CREATE INDEX idx_task_comments_task_id ON task_comments (task_id)');
        $this->addSql('CREATE INDEX idx_task_comments_author_id ON task_comments (author_id)');
        $this->addSql('CREATE INDEX idx_task_comments_created_at ON task_comments (created_at)');
        
        // Indexes for users table
        $this->addSql('CREATE INDEX idx_users_last_login ON users (last_login_at)');
        $this->addSql('CREATE INDEX idx_users_is_active ON users (is_active)');
        $this->addSql('CREATE INDEX idx_users_created_at ON users (created_at)');
        
        // Indexes for categories
        $this->addSql('CREATE INDEX idx_task_categories_user_id ON task_categories (user_id)');
        $this->addSql('CREATE INDEX idx_task_categories_created_at ON task_categories (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_task_completed_at');
        $this->addSql('DROP INDEX idx_task_updated_at');
        $this->addSql('DROP INDEX idx_task_created_at_status');
        $this->addSql('DROP INDEX idx_task_user_status');
        $this->addSql('DROP INDEX idx_task_user_priority');
        $this->addSql('DROP INDEX idx_task_assigned_user_status');
        $this->addSql('DROP INDEX idx_task_category_status');
        $this->addSql('DROP INDEX idx_task_due_date_status');
        
        // Drop indexes for comments table
        $this->addSql('DROP INDEX idx_task_comments_task_id');
        $this->addSql('DROP INDEX idx_task_comments_author_id');
        $this->addSql('DROP INDEX idx_task_comments_created_at');
        
        // Drop indexes for users table
        $this->addSql('DROP INDEX idx_users_last_login');
        $this->addSql('DROP INDEX idx_users_is_active');
        $this->addSql('DROP INDEX idx_users_created_at');
        
        // Drop indexes for categories
        $this->addSql('DROP INDEX idx_task_categories_user_id');
        $this->addSql('DROP INDEX idx_task_categories_created_at');
    }
}