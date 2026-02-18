<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218071125 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE goal_milestones (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, due_date DATETIME NOT NULL, completed BOOLEAN NOT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, goal_id INTEGER NOT NULL, CONSTRAINT FK_3AA39083667D1AFE FOREIGN KEY (goal_id) REFERENCES goals (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3AA39083667D1AFE ON goal_milestones (goal_id)');
        $this->addSql('CREATE TABLE goals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, target_value NUMERIC(5, 2) NOT NULL, current_value NUMERIC(5, 2) NOT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, owner_id INTEGER NOT NULL, CONSTRAINT FK_C7241E2F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C7241E2F7E3C61F9 ON goals (owner_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE goal_milestones');
        $this->addSql('DROP TABLE goals');
    }
}
