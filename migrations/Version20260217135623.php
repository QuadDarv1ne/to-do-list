<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217135623 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__tasks AS SELECT id, title, description, status, priority, created_at, updated_at, due_date, user_id, assigned_user_id, category_id, completed_at FROM tasks');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, priority VARCHAR(20) DEFAULT \'medium\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, due_date DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, assigned_user_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, completed_at DATETIME DEFAULT NULL, CONSTRAINT FK_50586597A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_50586597ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5058659712469DE2 FOREIGN KEY (category_id) REFERENCES task_categories (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tasks (id, title, description, status, priority, created_at, updated_at, due_date, user_id, assigned_user_id, category_id, completed_at) SELECT id, title, description, status, priority, created_at, updated_at, due_date, user_id, assigned_user_id, category_id, completed_at FROM __temp__tasks');
        $this->addSql('DROP TABLE __temp__tasks');
        $this->addSql('CREATE INDEX idx_task_priority ON tasks (priority)');
        $this->addSql('CREATE INDEX idx_task_created_at ON tasks (created_at)');
        $this->addSql('CREATE INDEX idx_task_category ON tasks (category_id)');
        $this->addSql('CREATE INDEX idx_task_user_status ON tasks (user_id, status)');
        $this->addSql('CREATE INDEX idx_task_assigned_user_status ON tasks (assigned_user_id, status)');
        $this->addSql('CREATE INDEX idx_task_due_date_status ON tasks (due_date, status)');
        $this->addSql('CREATE INDEX IDX_50586597A76ED395 ON tasks (user_id)');
        $this->addSql('CREATE INDEX IDX_50586597ADF66B1A ON tasks (assigned_user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__tasks AS SELECT id, title, description, status, priority, created_at, updated_at, due_date, completed_at, user_id, assigned_user_id, category_id FROM tasks');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, priority VARCHAR(20) DEFAULT \'medium\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, due_date DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, assigned_user_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, CONSTRAINT FK_50586597A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_50586597ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5058659712469DE2 FOREIGN KEY (category_id) REFERENCES task_categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tasks (id, title, description, status, priority, created_at, updated_at, due_date, completed_at, user_id, assigned_user_id, category_id) SELECT id, title, description, status, priority, created_at, updated_at, due_date, completed_at, user_id, assigned_user_id, category_id FROM __temp__tasks');
        $this->addSql('DROP TABLE __temp__tasks');
        $this->addSql('CREATE INDEX idx_task_user_status ON tasks (user_id, status)');
        $this->addSql('CREATE INDEX idx_task_assigned_user_status ON tasks (assigned_user_id, status)');
        $this->addSql('CREATE INDEX idx_task_due_date_status ON tasks (due_date, status)');
        $this->addSql('CREATE INDEX idx_task_category ON tasks (category_id)');
        $this->addSql('CREATE INDEX idx_task_created_at ON tasks (created_at)');
        $this->addSql('CREATE INDEX idx_task_priority ON tasks (priority)');
        $this->addSql('CREATE INDEX idx_task_status ON tasks (status)');
        $this->addSql('CREATE INDEX idx_task_due_date ON tasks (due_date)');
        $this->addSql('CREATE INDEX idx_task_completed_at ON tasks (completed_at)');
        $this->addSql('CREATE INDEX idx_task_updated_at ON tasks (updated_at)');
        $this->addSql('CREATE INDEX idx_task_created_at_status ON tasks (created_at, status)');
        $this->addSql('CREATE INDEX idx_task_user_priority ON tasks (user_id, priority)');
        $this->addSql('CREATE INDEX idx_task_category_status ON tasks (category_id, status)');
        $this->addSql('CREATE INDEX idx_task_user ON tasks (user_id)');
        $this->addSql('CREATE INDEX idx_task_assigned_user ON tasks (assigned_user_id)');
    }
}
