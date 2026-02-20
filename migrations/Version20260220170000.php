<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Base migration - Initial database schema
 * This migration creates all tables from scratch
 * Safe for both dev and prod environments
 */
final class Version20260220170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial database schema - all tables';
    }

    public function up(Schema $schema): void
    {
        // Check if tables already exist (for prod safety)
        $sm = $this->connection->createSchemaManager();
        $tables = $sm->listTableNames();
        
        if (in_array('users', $tables)) {
            $this->write('Tables already exist, skipping creation');
            return;
        }

        // Users table
        $this->addSql('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            username VARCHAR(180) NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles CLOB NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) DEFAULT NULL,
            last_name VARCHAR(100) DEFAULT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            position VARCHAR(255) DEFAULT NULL,
            department VARCHAR(255) DEFAULT NULL,
            is_active BOOLEAN DEFAULT 1 NOT NULL,
            last_login_at DATETIME DEFAULT NULL,
            notes CLOB DEFAULT NULL,
            avatar VARCHAR(255) DEFAULT NULL,
            totp_secret VARCHAR(255) DEFAULT NULL,
            totp_secret_temp VARCHAR(255) DEFAULT NULL,
            is_totp_enabled BOOLEAN DEFAULT 0 NOT NULL,
            totp_enabled_at DATETIME DEFAULT NULL,
            backup_codes CLOB DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            password_changed_at DATETIME DEFAULT NULL,
            locked_until DATETIME DEFAULT NULL,
            failed_login_attempts INTEGER DEFAULT 0 NOT NULL
        )');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON users (username)');
        $this->addSql('CREATE INDEX idx_user_email ON users (email)');
        $this->addSql('CREATE INDEX idx_user_created_at ON users (created_at)');
        $this->addSql('CREATE INDEX idx_user_last_login ON users (last_login_at)');
        $this->addSql('CREATE INDEX idx_users_is_active ON users (is_active)');

        // Task categories
        $this->addSql('CREATE TABLE task_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            user_id INTEGER NOT NULL,
            CONSTRAINT FK_26E00DC7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE INDEX idx_task_categories_user_id ON task_categories (user_id)');

        // Tasks table
        $this->addSql('CREATE TABLE tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            status VARCHAR(20) DEFAULT \'pending\' NOT NULL,
            priority VARCHAR(20) DEFAULT \'medium\' NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            due_date DATETIME DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            progress INTEGER DEFAULT 0 NOT NULL,
            user_id INTEGER NOT NULL,
            assigned_user_id INTEGER DEFAULT NULL,
            category_id INTEGER DEFAULT NULL,
            parent_id INTEGER DEFAULT NULL,
            CONSTRAINT FK_50586597A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_50586597ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_5058659712469DE2 FOREIGN KEY (category_id) REFERENCES task_categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_50586597727ACA70 FOREIGN KEY (parent_id) REFERENCES tasks (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE INDEX IDX_50586597A76ED395 ON tasks (user_id)');
        $this->addSql('CREATE INDEX IDX_50586597ADF66B1A ON tasks (assigned_user_id)');
        $this->addSql('CREATE INDEX IDX_50586597727ACA70 ON tasks (parent_id)');
        $this->addSql('CREATE INDEX idx_task_user_status ON tasks (user_id, status)');
        $this->addSql('CREATE INDEX idx_task_due_date_status ON tasks (due_date, status)');
        $this->addSql('CREATE INDEX idx_task_category ON tasks (category_id)');
        $this->addSql('CREATE INDEX idx_task_priority ON tasks (priority)');

        // Tags
        $this->addSql('CREATE TABLE tags (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR(50) NOT NULL,
            description CLOB DEFAULT NULL,
            color VARCHAR(7) DEFAULT \'#007bff\' NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            user_id INTEGER NOT NULL,
            CONSTRAINT FK_6FBC9426A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE INDEX IDX_6FBC9426A76ED395 ON tags (user_id)');

        // Task tags (many-to-many)
        $this->addSql('CREATE TABLE task_tags (
            task_id INTEGER NOT NULL,
            tag_id INTEGER NOT NULL,
            PRIMARY KEY (task_id, tag_id),
            CONSTRAINT FK_1C0F005D8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_1C0F005DBAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE INDEX IDX_1C0F005D8DB60186 ON task_tags (task_id)');
        $this->addSql('CREATE INDEX IDX_1C0F005DBAD26311 ON task_tags (tag_id)');

        // Comments
        $this->addSql('CREATE TABLE task_comments (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            content CLOB NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            author_id INTEGER NOT NULL,
            task_id INTEGER NOT NULL,
            CONSTRAINT FK_1F5E7C66F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_1F5E7C668DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE INDEX idx_task_comments_task_id ON task_comments (task_id)');
        $this->addSql('CREATE INDEX idx_task_comments_author_id ON task_comments (author_id)');

        // Notifications
        $this->addSql('CREATE TABLE notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message CLOB DEFAULT NULL,
            is_read BOOLEAN NOT NULL,
            created_at DATETIME NOT NULL,
            type VARCHAR(50) DEFAULT \'info\' NOT NULL,
            channel VARCHAR(50) DEFAULT \'in_app\' NOT NULL,
            status VARCHAR(50) DEFAULT \'pending\' NOT NULL,
            metadata CLOB DEFAULT NULL,
            sent_at DATETIME DEFAULT NULL,
            delivered_at DATETIME DEFAULT NULL,
            error_message CLOB DEFAULT NULL,
            template_key VARCHAR(100) DEFAULT NULL,
            user_id INTEGER NOT NULL,
            task_id INTEGER DEFAULT NULL,
            CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_6000B0D38DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE INDEX idx_user_read_created ON notifications (user_id, is_read, created_at)');
        $this->addSql('CREATE INDEX idx_status ON notifications (status)');

        // Add remaining tables (abbreviated for brevity)
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

        // Task time tracking
        $this->addSql('CREATE TABLE task_time_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            time_spent TIME NOT NULL,
            description CLOB DEFAULT NULL,
            date_logged DATETIME NOT NULL,
            user_id INTEGER NOT NULL,
            task_id INTEGER NOT NULL,
            CONSTRAINT FK_49EEEC81A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_49EEEC818DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE INDEX IDX_49EEEC81A76ED395 ON task_time_tracking (user_id)');
        $this->addSql('CREATE INDEX IDX_49EEEC818DB60186 ON task_time_tracking (task_id)');

        // Task recurrences
        $this->addSql('CREATE TABLE task_recurrences (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            frequency VARCHAR(20) NOT NULL,
            end_date DATE DEFAULT NULL,
            interval INTEGER NOT NULL,
            days_of_week CLOB DEFAULT NULL,
            days_of_month CLOB DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            last_generated DATE DEFAULT NULL,
            task_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            CONSTRAINT FK_110DC5958DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE,
            CONSTRAINT FK_110DC595A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        
        $this->addSql('CREATE UNIQUE INDEX UNIQ_110DC5958DB60186 ON task_recurrences (task_id)');

        // Task templates
        $this->addSql('CREATE TABLE task_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            is_public BOOLEAN DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            user_id INTEGER NOT NULL,
            CONSTRAINT FK_1002B0DFA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        $this->addSql('CREATE TABLE task_template_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description CLOB DEFAULT NULL,
            priority VARCHAR(20) DEFAULT \'medium\' NOT NULL,
            sort_order INTEGER DEFAULT 0 NOT NULL,
            template_id INTEGER NOT NULL,
            CONSTRAINT FK_CEE14C2D5DA0FB8 FOREIGN KEY (template_id) REFERENCES task_templates (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        // Webhooks
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

        // Messenger messages
        $this->addSql('CREATE TABLE messenger_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            body CLOB NOT NULL,
            headers CLOB NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at DATETIME NOT NULL,
            available_at DATETIME NOT NULL,
            delivered_at DATETIME DEFAULT NULL
        )');
        
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');

        // Reset password requests
        $this->addSql('CREATE TABLE reset_password_request (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            selector VARCHAR(20) NOT NULL,
            hashed_token VARCHAR(100) NOT NULL,
            requested_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            user_id INTEGER NOT NULL,
            CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');

        $this->write('Base schema created successfully');
    }

    public function down(Schema $schema): void
    {
        // Drop all tables in reverse order
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('DROP TABLE IF EXISTS webhook_logs');
        $this->addSql('DROP TABLE IF EXISTS webhooks');
        $this->addSql('DROP TABLE IF EXISTS task_template_items');
        $this->addSql('DROP TABLE IF EXISTS task_templates');
        $this->addSql('DROP TABLE IF EXISTS task_recurrences');
        $this->addSql('DROP TABLE IF EXISTS task_time_tracking');
        $this->addSql('DROP TABLE IF EXISTS notification_templates');
        $this->addSql('DROP TABLE IF EXISTS notifications');
        $this->addSql('DROP TABLE IF EXISTS task_comments');
        $this->addSql('DROP TABLE IF EXISTS task_tags');
        $this->addSql('DROP TABLE IF EXISTS tags');
        $this->addSql('DROP TABLE IF EXISTS tasks');
        $this->addSql('DROP TABLE IF EXISTS task_categories');
        $this->addSql('DROP TABLE IF EXISTS reset_password_request');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
