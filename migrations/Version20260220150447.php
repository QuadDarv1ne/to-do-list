<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220150447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Создаём таблицу notification_templates только если не существует
        if (!$schema->hasTable('notification_templates')) {
            $this->addSql('CREATE TABLE notification_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                "key" VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                subject CLOB NOT NULL,
                content CLOB NOT NULL,
                channel VARCHAR(50) NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                variables CLOB DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL
            )');
            $this->addSql('CREATE UNIQUE INDEX UNIQ_C9C13AD18A90ABA9 ON notification_templates ("key")');
            $this->addSql('CREATE INDEX idx_key_active ON notification_templates ("key", is_active)');
            $this->addSql('CREATE INDEX idx_channel ON notification_templates (channel)');
        }

        // Создаём таблицу task_template_items только если не существует
        if (!$schema->hasTable('task_template_items')) {
            $this->addSql('CREATE TABLE task_template_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                title VARCHAR(255) NOT NULL,
                description CLOB DEFAULT NULL,
                priority VARCHAR(20) DEFAULT \'medium\' NOT NULL,
                sort_order INTEGER DEFAULT 0 NOT NULL,
                template_id INTEGER NOT NULL,
                CONSTRAINT FK_CEE14C2D5DA0FB8 FOREIGN KEY (template_id) REFERENCES task_templates (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            )');
            $this->addSql('CREATE INDEX IDX_CEE14C2D5DA0FB8 ON task_template_items (template_id)');
        }

        // Создаём таблицу task_templates только если не существует
        if (!$schema->hasTable('task_templates')) {
            $this->addSql('CREATE TABLE task_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                description CLOB DEFAULT NULL,
                is_public BOOLEAN DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                user_id INTEGER NOT NULL,
                CONSTRAINT FK_1002B0DFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )');
            $this->addSql('CREATE INDEX idx_task_template_user ON task_templates (user_id)');
        }

        // Создаём таблицу webhook_logs только если не существует
        if (!$schema->hasTable('webhook_logs')) {
            $this->addSql('CREATE TABLE webhook_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                created_at DATETIME NOT NULL,
                payload CLOB NOT NULL,
                response CLOB DEFAULT NULL,
                event VARCHAR(100) NOT NULL,
                status_code INTEGER DEFAULT NULL,
                response_time_ms INTEGER DEFAULT NULL,
                is_success BOOLEAN NOT NULL,
                error_message CLOB DEFAULT NULL,
                webhook_id INTEGER NOT NULL,
                CONSTRAINT FK_45A353475C9BA60B FOREIGN KEY (webhook_id) REFERENCES webhooks (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )');
            $this->addSql('CREATE INDEX idx_webhook_log_webhook ON webhook_logs (webhook_id)');
            $this->addSql('CREATE INDEX idx_webhook_log_event ON webhook_logs (event)');
            $this->addSql('CREATE INDEX idx_webhook_log_created ON webhook_logs (created_at)');
            $this->addSql('CREATE INDEX idx_webhook_log_success ON webhook_logs (is_success)');
        }

        // Создаём таблицу webhooks только если не существует
        if (!$schema->hasTable('webhooks')) {
            $this->addSql('CREATE TABLE webhooks (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                name VARCHAR(255) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                secret VARCHAR(64) DEFAULT NULL,
                events CLOB NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                last_triggered_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                user_id INTEGER NOT NULL,
                CONSTRAINT FK_998C4FDDA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
            )');
            $this->addSql('CREATE INDEX IDX_998C4FDDA76ED395 ON webhooks (user_id)');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE notification_templates');
        $this->addSql('DROP TABLE task_template_items');
        $this->addSql('DROP TABLE task_templates');
        $this->addSql('DROP TABLE webhook_logs');
        $this->addSql('DROP TABLE webhooks');
    }
}
