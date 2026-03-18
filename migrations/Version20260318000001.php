<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Создание таблицы push_notifications для системы уведомлений
 */
final class Version20260318000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблицы push_notifications для real-time уведомлений';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS push_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message VARCHAR(500) NOT NULL,
                action_url VARCHAR(255) DEFAULT NULL,
                data CLOB DEFAULT NULL,
                is_read BOOLEAN DEFAULT 0 NOT NULL,
                created_at DATETIME NOT NULL,
                read_at DATETIME DEFAULT NULL,
                channel VARCHAR(100) DEFAULT NULL,
                CONSTRAINT FK_PUSH_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');

        // Индексы для производительности
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_push_user ON push_notifications (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_push_is_read ON push_notifications (is_read)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_push_created ON push_notifications (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS push_notifications');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
