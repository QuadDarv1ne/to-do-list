<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220161131 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE time_tracking_sessions');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_time_tracking AS SELECT id, time_spent, description, date_logged, user_id, task_id FROM task_time_tracking');
        $this->addSql('DROP TABLE task_time_tracking');
        $this->addSql('CREATE TABLE task_time_tracking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, time_spent TIME NOT NULL, description CLOB DEFAULT NULL, date_logged DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_49EEEC81A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_49EEEC818DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_time_tracking (id, time_spent, description, date_logged, user_id, task_id) SELECT id, time_spent, description, date_logged, user_id, task_id FROM __temp__task_time_tracking');
        $this->addSql('DROP TABLE __temp__task_time_tracking');
        $this->addSql('CREATE INDEX IDX_49EEEC818DB60186 ON task_time_tracking (task_id)');
        $this->addSql('CREATE INDEX IDX_49EEEC81A76ED395 ON task_time_tracking (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE time_tracking_sessions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, duration_seconds INTEGER DEFAULT 0, description CLOB DEFAULT NULL COLLATE "BINARY", is_active BOOLEAN DEFAULT 1 NOT NULL, CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_session_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_time_session_active ON time_tracking_sessions (is_active)');
        $this->addSql('CREATE INDEX idx_time_session_task ON time_tracking_sessions (task_id)');
        $this->addSql('CREATE INDEX idx_time_session_user ON time_tracking_sessions (user_id)');
        $this->addSql('ALTER TABLE task_time_tracking ADD COLUMN started_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE task_time_tracking ADD COLUMN ended_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE task_time_tracking ADD COLUMN duration_seconds INTEGER DEFAULT 0');
        $this->addSql('ALTER TABLE task_time_tracking ADD COLUMN is_active BOOLEAN DEFAULT 0');
    }
}
