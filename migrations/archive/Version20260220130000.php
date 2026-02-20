<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Создание таблицы webhooks и webhook_logs
 */
final class Version20260220130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create webhooks and webhook_logs tables';
    }

    public function up(Schema $schema): void
    {
        // Таблица webhooks (если не существует)
        if (!$schema->hasTable('webhooks')) {
            $this->addSql('CREATE TABLE webhooks (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                name VARCHAR(255) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                secret VARCHAR(64) DEFAULT NULL,
                events CLOB NOT NULL COMMENT \'(DC2Type:json)\',
                is_active BOOLEAN NOT NULL DEFAULT 1,
                last_triggered_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                CONSTRAINT fk_webhook_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )');
            
            $this->addSql('CREATE INDEX idx_webhook_user ON webhooks (user_id)');
            $this->addSql('CREATE INDEX idx_webhook_active ON webhooks (is_active)');
            $this->addSql('CREATE INDEX idx_webhook_user_active ON webhooks (user_id, is_active)');
        }

        // Таблица webhook_logs (если не существует)
        if (!$schema->hasTable('webhook_logs')) {
            $this->addSql('CREATE TABLE webhook_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                webhook_id INTEGER NOT NULL,
                event VARCHAR(100) NOT NULL,
                status_code INTEGER DEFAULT NULL,
                response_time_ms INTEGER DEFAULT NULL,
                is_success BOOLEAN NOT NULL DEFAULT 0,
                error_message CLOB DEFAULT NULL,
                payload CLOB NOT NULL COMMENT \'(DC2Type:json)\',
                response CLOB DEFAULT NULL COMMENT \'(DC2Type:json)\',
                created_at DATETIME NOT NULL,
                CONSTRAINT fk_webhook_log_webhook FOREIGN KEY (webhook_id) REFERENCES webhooks (id) ON DELETE CASCADE
            )');
            
            $this->addSql('CREATE INDEX idx_webhook_log_webhook ON webhook_logs (webhook_id)');
            $this->addSql('CREATE INDEX idx_webhook_log_event ON webhook_logs (event)');
            $this->addSql('CREATE INDEX idx_webhook_log_created ON webhook_logs (created_at)');
            $this->addSql('CREATE INDEX idx_webhook_log_success ON webhook_logs (is_success)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE webhook_logs');
        $this->addSql('DROP TABLE webhooks');
    }
}
