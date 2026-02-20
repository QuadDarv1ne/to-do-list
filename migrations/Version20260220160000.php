<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Обновление таблиц для повторяющихся задач и учёта времени
 */
final class Version20260220160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Update task_recurrences and task_time_tracking tables';
    }

    public function up(Schema $schema): void
    {
        // Обновление task_recurrences (если таблица существует)
        if ($schema->hasTable('task_recurrences')) {
            // Проверяем, существует ли колонка interval
            if (!$schema->getTable('task_recurrences')->hasColumn('interval')) {
                $this->addSql('ALTER TABLE task_recurrences ADD COLUMN "interval" INTEGER DEFAULT 1');
            }
            
            // Проверяем, существует ли колонка days_of_week
            if (!$schema->getTable('task_recurrences')->hasColumn('days_of_week')) {
                $this->addSql('ALTER TABLE task_recurrences ADD COLUMN days_of_week CLOB DEFAULT NULL');
            }
            
            // Проверяем, существует ли колонка days_of_month
            if (!$schema->getTable('task_recurrences')->hasColumn('days_of_month')) {
                $this->addSql('ALTER TABLE task_recurrences ADD COLUMN days_of_month CLOB DEFAULT NULL');
            }
            
            // Проверяем, существует ли колонка last_generated
            if (!$schema->getTable('task_recurrences')->hasColumn('last_generated')) {
                $this->addSql('ALTER TABLE task_recurrences ADD COLUMN last_generated DATETIME DEFAULT NULL');
            }
        }

        // Обновление task_time_tracking (если таблица существует)
        if ($schema->hasTable('task_time_tracking')) {
            // Проверяем, существует ли колонка started_at
            if (!$schema->getTable('task_time_tracking')->hasColumn('started_at')) {
                $this->addSql('ALTER TABLE task_time_tracking ADD COLUMN started_at DATETIME DEFAULT NULL');
            }
            
            // Проверяем, существует ли колонка ended_at
            if (!$schema->getTable('task_time_tracking')->hasColumn('ended_at')) {
                $this->addSql('ALTER TABLE task_time_tracking ADD COLUMN ended_at DATETIME DEFAULT NULL');
            }
            
            // Проверяем, существует ли колонка duration_seconds
            if (!$schema->getTable('task_time_tracking')->hasColumn('duration_seconds')) {
                $this->addSql('ALTER TABLE task_time_tracking ADD COLUMN duration_seconds INTEGER DEFAULT 0');
            }
            
            // Проверяем, существует ли колонка is_active
            if (!$schema->getTable('task_time_tracking')->hasColumn('is_active')) {
                $this->addSql('ALTER TABLE task_time_tracking ADD COLUMN is_active BOOLEAN DEFAULT 0');
            }
        }

        // Создание новой таблицы для активных сессий учёта времени (опционально)
        if (!$schema->hasTable('time_tracking_sessions')) {
            $this->addSql('CREATE TABLE time_tracking_sessions (
                id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                user_id INTEGER NOT NULL,
                task_id INTEGER NOT NULL,
                started_at DATETIME NOT NULL,
                ended_at DATETIME DEFAULT NULL,
                duration_seconds INTEGER DEFAULT 0,
                description CLOB DEFAULT NULL,
                is_active BOOLEAN NOT NULL DEFAULT 1,
                CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_session_task FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE
            )');
            
            $this->addSql('CREATE INDEX idx_time_session_user ON time_tracking_sessions (user_id)');
            $this->addSql('CREATE INDEX idx_time_session_task ON time_tracking_sessions (task_id)');
            $this->addSql('CREATE INDEX idx_time_session_active ON time_tracking_sessions (is_active)');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE time_tracking_sessions');
        
        if ($schema->hasTable('task_time_tracking')) {
            $this->addSql('ALTER TABLE task_time_tracking DROP COLUMN started_at');
            $this->addSql('ALTER TABLE task_time_tracking DROP COLUMN ended_at');
            $this->addSql('ALTER TABLE task_time_tracking DROP COLUMN duration_seconds');
            $this->addSql('ALTER TABLE task_time_tracking DROP COLUMN is_active');
        }
        
        if ($schema->hasTable('task_recurrences')) {
            $this->addSql('ALTER TABLE task_recurrences DROP COLUMN interval');
            $this->addSql('ALTER TABLE task_recurrences DROP COLUMN days_of_week');
            $this->addSql('ALTER TABLE task_recurrences DROP COLUMN days_of_month');
            $this->addSql('ALTER TABLE task_recurrences DROP COLUMN last_generated');
        }
    }
}
