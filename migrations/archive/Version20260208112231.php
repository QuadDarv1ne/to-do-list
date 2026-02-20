<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208112231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" CLOB NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F34B1DCE8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_F34B1DCEA76ED395 ON activity_logs (user_id)');
        $this->addSql('CREATE INDEX IDX_F34B1DCE8DB60186 ON activity_logs (task_id)');
        $this->addSql('CREATE TABLE task_category_task (task_id INTEGER NOT NULL, task_category_id INTEGER NOT NULL, PRIMARY KEY (task_id, task_category_id), CONSTRAINT FK_9CF26C338DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9CF26C33543330D0 FOREIGN KEY (task_category_id) REFERENCES task_categories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9CF26C338DB60186 ON task_category_task (task_id)');
        $this->addSql('CREATE INDEX IDX_9CF26C33543330D0 ON task_category_task (task_category_id)');
        $this->addSql('CREATE TABLE task_categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INTEGER NOT NULL, CONSTRAINT FK_26E00DC77E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_26E00DC77E3C61F9 ON task_categories (owner_id)');
        $this->addSql('CREATE TABLE task_recurrences (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, frequency VARCHAR(20) NOT NULL, end_date DATE DEFAULT NULL, interval INTEGER NOT NULL, days_of_week CLOB DEFAULT NULL, days_of_month CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_110DC5958DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_110DC5958DB60186 ON task_recurrences (task_id)');
        $this->addSql('CREATE TABLE task_time_tracking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, time_spent TIME NOT NULL, description CLOB DEFAULT NULL, date_logged DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_49EEEC81A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_49EEEC818DB60186 FOREIGN KEY (task_id) REFERENCES task (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_49EEEC81A76ED395 ON task_time_tracking (user_id)');
        $this->addSql('CREATE INDEX IDX_49EEEC818DB60186 ON task_time_tracking (task_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE activity_logs');
        $this->addSql('DROP TABLE task_category_task');
        $this->addSql('DROP TABLE task_categories');
        $this->addSql('DROP TABLE task_recurrences');
        $this->addSql('DROP TABLE task_time_tracking');
    }
}
