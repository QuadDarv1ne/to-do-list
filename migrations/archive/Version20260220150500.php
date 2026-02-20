<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create notification templates table
 */
final class Version20260220150500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notification templates table';
    }

    public function up(Schema $schema): void
    {
        // Создаём таблицу только если не существует
        if (!$schema->hasTable('notification_templates')) {
            $this->addSql('CREATE TABLE notification_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                key VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                subject TEXT NOT NULL,
                content TEXT NOT NULL,
                channel VARCHAR(50) NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                variables TEXT,
                created_at DATETIME NOT NULL,
                updated_at DATETIME
            )');

            $this->addSql('CREATE UNIQUE INDEX UNIQ_C9C13AD18A90ABA9 ON notification_templates (key)');
            $this->addSql('CREATE INDEX idx_key_active ON notification_templates (key, is_active)');
            $this->addSql('CREATE INDEX idx_channel ON notification_templates (channel)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_templates');
    }
}