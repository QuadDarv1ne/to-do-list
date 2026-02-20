<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create task_templates and task_template_items tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            is_public BOOLEAN NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            CONSTRAINT fk_task_template_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_task_template_user ON task_templates (user_id)');

        $this->addSql('CREATE TABLE task_template_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            template_id INTEGER NOT NULL,
            title VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            priority VARCHAR(20) NOT NULL DEFAULT \'medium\',
            sort_order INTEGER NOT NULL DEFAULT 0,
            CONSTRAINT fk_task_template_item_template FOREIGN KEY (template_id) REFERENCES task_templates (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_task_template_item_template ON task_template_items (template_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task_template_items');
        $this->addSql('DROP TABLE task_templates');
    }
}
