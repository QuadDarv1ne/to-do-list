<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209090442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task_recurrences ADD COLUMN last_generated DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_recurrences AS SELECT id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id, user_id FROM task_recurrences');
        $this->addSql('DROP TABLE task_recurrences');
        $this->addSql('CREATE TABLE task_recurrences (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, frequency VARCHAR(20) NOT NULL, end_date DATE DEFAULT NULL, interval INTEGER NOT NULL, days_of_week CLOB DEFAULT NULL, days_of_month CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, task_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_110DC5958DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_110DC595A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_recurrences (id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id, user_id) SELECT id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id, user_id FROM __temp__task_recurrences');
        $this->addSql('DROP TABLE __temp__task_recurrences');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_110DC5958DB60186 ON task_recurrences (task_id)');
        $this->addSql('CREATE INDEX IDX_110DC595A76ED395 ON task_recurrences (user_id)');
    }
}
