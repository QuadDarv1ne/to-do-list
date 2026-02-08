<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208125715 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, priority VARCHAR(20) DEFAULT \'medium\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, due_date DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, assigned_user_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, CONSTRAINT FK_50586597A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_50586597ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5058659712469DE2 FOREIGN KEY (category_id) REFERENCES task_categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_50586597A76ED395 ON tasks (user_id)');
        $this->addSql('CREATE INDEX IDX_50586597ADF66B1A ON tasks (assigned_user_id)');
        $this->addSql('CREATE INDEX IDX_5058659712469DE2 ON tasks (category_id)');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_category_task');
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity_logs AS SELECT id, "action", description, created_at, user_id, task_id FROM activity_logs');
        $this->addSql('DROP TABLE activity_logs');
        $this->addSql('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" CLOB NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F34B1DCE8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity_logs (id, "action", description, created_at, user_id, task_id) SELECT id, "action", description, created_at, user_id, task_id FROM __temp__activity_logs');
        $this->addSql('DROP TABLE __temp__activity_logs');
        $this->addSql('CREATE INDEX IDX_F34B1DCE8DB60186 ON activity_logs (task_id)');
        $this->addSql('CREATE INDEX IDX_F34B1DCEA76ED395 ON activity_logs (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__notifications AS SELECT id, title, message, is_read, created_at, user_id, task_id FROM notifications');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, message CLOB DEFAULT NULL, is_read BOOLEAN NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER DEFAULT NULL, CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6000B0D38DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO notifications (id, title, message, is_read, created_at, user_id, task_id) SELECT id, title, message, is_read, created_at, user_id, task_id FROM __temp__notifications');
        $this->addSql('DROP TABLE __temp__notifications');
        $this->addSql('CREATE INDEX IDX_6000B0D38DB60186 ON notifications (task_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_categories AS SELECT id, name, description, created_at, updated_at, owner_id FROM task_categories');
        $this->addSql('DROP TABLE task_categories');
        $this->addSql('CREATE TABLE task_categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_26E00DC7A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_categories (id, name, description, created_at, updated_at, user_id) SELECT id, name, description, created_at, updated_at, owner_id FROM __temp__task_categories');
        $this->addSql('DROP TABLE __temp__task_categories');
        $this->addSql('CREATE INDEX IDX_26E00DC7A76ED395 ON task_categories (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_comments AS SELECT id, content, created_at, updated_at, author_id, task_id FROM task_comments');
        $this->addSql('DROP TABLE task_comments');
        $this->addSql('CREATE TABLE task_comments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, author_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_1F5E7C66F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F5E7C668DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_comments (id, content, created_at, updated_at, author_id, task_id) SELECT id, content, created_at, updated_at, author_id, task_id FROM __temp__task_comments');
        $this->addSql('DROP TABLE __temp__task_comments');
        $this->addSql('CREATE INDEX IDX_1F5E7C668DB60186 ON task_comments (task_id)');
        $this->addSql('CREATE INDEX IDX_1F5E7C66F675F31B ON task_comments (author_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_notifications AS SELECT id, type, subject, message, is_sent, is_read, created_at, sent_at, read_at, task_id, recipient_id, sender_id FROM task_notifications');
        $this->addSql('DROP TABLE task_notifications');
        $this->addSql('CREATE TABLE task_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(100) NOT NULL, subject CLOB NOT NULL, message CLOB NOT NULL, is_sent BOOLEAN NOT NULL, is_read BOOLEAN NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, task_id INTEGER NOT NULL, recipient_id INTEGER NOT NULL, sender_id INTEGER NOT NULL, CONSTRAINT FK_25177D82E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_25177D82F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_25177D828DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_notifications (id, type, subject, message, is_sent, is_read, created_at, sent_at, read_at, task_id, recipient_id, sender_id) SELECT id, type, subject, message, is_sent, is_read, created_at, sent_at, read_at, task_id, recipient_id, sender_id FROM __temp__task_notifications');
        $this->addSql('DROP TABLE __temp__task_notifications');
        $this->addSql('CREATE INDEX IDX_25177D82F624B39D ON task_notifications (sender_id)');
        $this->addSql('CREATE INDEX IDX_25177D82E92F8F78 ON task_notifications (recipient_id)');
        $this->addSql('CREATE INDEX IDX_25177D828DB60186 ON task_notifications (task_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_recurrences AS SELECT id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id FROM task_recurrences');
        $this->addSql('DROP TABLE task_recurrences');
        $this->addSql('CREATE TABLE task_recurrences (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, frequency VARCHAR(20) NOT NULL, end_date DATE DEFAULT NULL, interval INTEGER NOT NULL, days_of_week CLOB DEFAULT NULL, days_of_month CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, task_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_110DC5958DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_110DC595A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_recurrences (id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id) SELECT id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id FROM __temp__task_recurrences');
        $this->addSql('DROP TABLE __temp__task_recurrences');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_110DC5958DB60186 ON task_recurrences (task_id)');
        $this->addSql('CREATE INDEX IDX_110DC595A76ED395 ON task_recurrences (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_time_tracking AS SELECT id, time_spent, description, date_logged, user_id, task_id FROM task_time_tracking');
        $this->addSql('DROP TABLE task_time_tracking');
        $this->addSql('CREATE TABLE task_time_tracking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, time_spent TIME NOT NULL, description CLOB DEFAULT NULL, date_logged DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_49EEEC81A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_49EEEC818DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_time_tracking (id, time_spent, description, date_logged, user_id, task_id) SELECT id, time_spent, description, date_logged, user_id, task_id FROM __temp__task_time_tracking');
        $this->addSql('DROP TABLE __temp__task_time_tracking');
        $this->addSql('CREATE INDEX IDX_49EEEC818DB60186 ON task_time_tracking (task_id)');
        $this->addSql('CREATE INDEX IDX_49EEEC81A76ED395 ON task_time_tracking (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, created_at, updated_at, last_login_at, password_changed_at FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, position VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, notes CLOB DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, password_changed_at DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO users (id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, created_at, updated_at, last_login_at, password_changed_at) SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, created_at, updated_at, last_login_at, password_changed_at FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL COLLATE "BINARY", description CLOB NOT NULL COLLATE "BINARY", is_done BOOLEAN NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL, assigned_user_id INTEGER DEFAULT NULL, deadline DATE DEFAULT NULL, priority VARCHAR(20) DEFAULT \'normal\' NOT NULL COLLATE "BINARY", created_by_id INTEGER NOT NULL, CONSTRAINT FK_527EDB25ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_527EDB25B03A8386 ON task (created_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25ADF66B1A ON task (assigned_user_id)');
        $this->addSql('CREATE TABLE task_category_task (task_id INTEGER NOT NULL, task_category_id INTEGER NOT NULL, PRIMARY KEY (task_id, task_category_id), CONSTRAINT FK_9CF26C338DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9CF26C33543330D0 FOREIGN KEY (task_category_id) REFERENCES task_categories (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9CF26C33543330D0 ON task_category_task (task_category_id)');
        $this->addSql('CREATE INDEX IDX_9CF26C338DB60186 ON task_category_task (task_id)');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity_logs AS SELECT id, "action", description, created_at, user_id, task_id FROM activity_logs');
        $this->addSql('DROP TABLE activity_logs');
        $this->addSql('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" CLOB NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F34B1DCE8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity_logs (id, "action", description, created_at, user_id, task_id) SELECT id, "action", description, created_at, user_id, task_id FROM __temp__activity_logs');
        $this->addSql('DROP TABLE __temp__activity_logs');
        $this->addSql('CREATE INDEX IDX_F34B1DCEA76ED395 ON activity_logs (user_id)');
        $this->addSql('CREATE INDEX IDX_F34B1DCE8DB60186 ON activity_logs (task_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__notifications AS SELECT id, title, message, is_read, created_at, user_id, task_id FROM notifications');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, message CLOB DEFAULT NULL, is_read BOOLEAN NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER DEFAULT NULL, CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6000B0D38DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO notifications (id, title, message, is_read, created_at, user_id, task_id) SELECT id, title, message, is_read, created_at, user_id, task_id FROM __temp__notifications');
        $this->addSql('DROP TABLE __temp__notifications');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D38DB60186 ON notifications (task_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_categories AS SELECT id, name, description, created_at, updated_at, user_id FROM task_categories');
        $this->addSql('DROP TABLE task_categories');
        $this->addSql('CREATE TABLE task_categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, owner_id INTEGER NOT NULL, CONSTRAINT FK_26E00DC77E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_categories (id, name, description, created_at, updated_at, owner_id) SELECT id, name, description, created_at, updated_at, user_id FROM __temp__task_categories');
        $this->addSql('DROP TABLE __temp__task_categories');
        $this->addSql('CREATE INDEX IDX_26E00DC77E3C61F9 ON task_categories (owner_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_comments AS SELECT id, content, created_at, updated_at, author_id, task_id FROM task_comments');
        $this->addSql('DROP TABLE task_comments');
        $this->addSql('CREATE TABLE task_comments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, author_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_1F5E7C66F675F31B FOREIGN KEY (author_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F5E7C668DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_comments (id, content, created_at, updated_at, author_id, task_id) SELECT id, content, created_at, updated_at, author_id, task_id FROM __temp__task_comments');
        $this->addSql('DROP TABLE __temp__task_comments');
        $this->addSql('CREATE INDEX IDX_1F5E7C66F675F31B ON task_comments (author_id)');
        $this->addSql('CREATE INDEX IDX_1F5E7C668DB60186 ON task_comments (task_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_notifications AS SELECT id, type, subject, message, is_sent, is_read, created_at, sent_at, read_at, task_id, recipient_id, sender_id FROM task_notifications');
        $this->addSql('DROP TABLE task_notifications');
        $this->addSql('CREATE TABLE task_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(100) NOT NULL, subject CLOB NOT NULL, message CLOB NOT NULL, is_sent BOOLEAN NOT NULL, is_read BOOLEAN NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, task_id INTEGER NOT NULL, recipient_id INTEGER NOT NULL, sender_id INTEGER NOT NULL, CONSTRAINT FK_25177D82E92F8F78 FOREIGN KEY (recipient_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_25177D82F624B39D FOREIGN KEY (sender_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_25177D828DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_notifications (id, type, subject, message, is_sent, is_read, created_at, sent_at, read_at, task_id, recipient_id, sender_id) SELECT id, type, subject, message, is_sent, is_read, created_at, sent_at, read_at, task_id, recipient_id, sender_id FROM __temp__task_notifications');
        $this->addSql('DROP TABLE __temp__task_notifications');
        $this->addSql('CREATE INDEX IDX_25177D828DB60186 ON task_notifications (task_id)');
        $this->addSql('CREATE INDEX IDX_25177D82E92F8F78 ON task_notifications (recipient_id)');
        $this->addSql('CREATE INDEX IDX_25177D82F624B39D ON task_notifications (sender_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_recurrences AS SELECT id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id FROM task_recurrences');
        $this->addSql('DROP TABLE task_recurrences');
        $this->addSql('CREATE TABLE task_recurrences (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, frequency VARCHAR(20) NOT NULL, end_date DATE DEFAULT NULL, interval INTEGER NOT NULL, days_of_week CLOB DEFAULT NULL, days_of_month CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_110DC5958DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_recurrences (id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id) SELECT id, frequency, end_date, interval, days_of_week, days_of_month, created_at, updated_at, task_id FROM __temp__task_recurrences');
        $this->addSql('DROP TABLE __temp__task_recurrences');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_110DC5958DB60186 ON task_recurrences (task_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task_time_tracking AS SELECT id, time_spent, description, date_logged, user_id, task_id FROM task_time_tracking');
        $this->addSql('DROP TABLE task_time_tracking');
        $this->addSql('CREATE TABLE task_time_tracking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, time_spent TIME NOT NULL, description CLOB DEFAULT NULL, date_logged DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_49EEEC81A76ED395 FOREIGN KEY (user_id) REFERENCES "users" (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_49EEEC818DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task_time_tracking (id, time_spent, description, date_logged, user_id, task_id) SELECT id, time_spent, description, date_logged, user_id, task_id FROM __temp__task_time_tracking');
        $this->addSql('DROP TABLE __temp__task_time_tracking');
        $this->addSql('CREATE INDEX IDX_49EEEC81A76ED395 ON task_time_tracking (user_id)');
        $this->addSql('CREATE INDEX IDX_49EEEC818DB60186 ON task_time_tracking (task_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, is_active, last_login_at, notes, created_at, updated_at, password_changed_at FROM "users"');
        $this->addSql('DROP TABLE "users"');
        $this->addSql('CREATE TABLE "users" (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, position VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, last_login_at DATETIME DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, password_changed_at DATETIME DEFAULT NULL, is_verified BOOLEAN DEFAULT 0 NOT NULL, timezone VARCHAR(100) DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL, failed_login_attempts SMALLINT DEFAULT 0 NOT NULL, locked_until DATETIME DEFAULT NULL, avatar VARCHAR(64) DEFAULT NULL, reset_password_token VARCHAR(255) DEFAULT NULL, reset_password_requested_at DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO "users" (id, username, email, roles, password, first_name, last_name, phone, position, department, is_active, last_login_at, notes, created_at, updated_at, password_changed_at) SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, is_active, last_login_at, notes, created_at, updated_at, password_changed_at FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON "users" (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON "users" (email)');
    }
}
