<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Создание таблицы audit_logs для журнала аудита
 */
final class Version20260318000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблицы audit_logs для журнала аудита действий пользователей';
    }

    public function up(Schema $schema): void
    {
        // Создаём таблицу audit_logs если она не существует
        $this->addSql('
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_class VARCHAR(255) NOT NULL,
                entity_id VARCHAR(255) NOT NULL,
                action VARCHAR(50) NOT NULL,
                changes CLOB DEFAULT NULL,
                old_values CLOB DEFAULT NULL,
                new_values CLOB DEFAULT NULL,
                user_id INTEGER DEFAULT NULL,
                user_name VARCHAR(255) DEFAULT NULL,
                user_email VARCHAR(255) DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                reason CLOB DEFAULT NULL,
                created_at DATETIME NOT NULL,
                CONSTRAINT FK_AUDIT_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            )
        ');

        // Создаём индексы для ускорения поиска
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_logs (entity_class, entity_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_logs (action)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_logs (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS audit_logs');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
