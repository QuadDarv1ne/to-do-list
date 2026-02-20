<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent_task_id to tasks table for subtask support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD COLUMN parent_task_id INTEGER DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_task_parent ON tasks (parent_task_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_task_parent');
    }
}
