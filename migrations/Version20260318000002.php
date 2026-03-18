<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Создание таблицы dashboard_widget для кастомизации дашборда
 */
final class Version20260318000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Создание таблицы dashboard_widget для пользовательских виджетов';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE IF NOT EXISTS dashboard_widget (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(255) NOT NULL,
                configuration CLOB DEFAULT NULL,
                position INTEGER DEFAULT 0 NOT NULL,
                width INTEGER DEFAULT 1 NOT NULL,
                is_active BOOLEAN DEFAULT 1 NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                CONSTRAINT FK_DASHBOARD_WIDGET_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');

        // Индексы для производительности
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dashboard_widget_user ON dashboard_widget (user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_dashboard_widget_type ON dashboard_widget (type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS dashboard_widget');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
