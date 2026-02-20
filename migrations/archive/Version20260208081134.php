<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208081134 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__task AS SELECT id, name, description, is_done, created_at, update_at FROM task');
        $this->addSql('DROP TABLE task');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB NOT NULL, is_done BOOLEAN NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL, assigned_user_id INTEGER NOT NULL, CONSTRAINT FK_527EDB25ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task (id, name, description, is_done, created_at, update_at) SELECT id, name, description, is_done, created_at, update_at FROM __temp__task');
        $this->addSql('DROP TABLE __temp__task');
        $this->addSql('CREATE INDEX IDX_527EDB25ADF66B1A ON task (assigned_user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_verified, created_at, updated_at, last_login_at, password_changed_at, timezone, locale, failed_login_attempts, locked_until, avatar FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, position VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, notes CLOB DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, is_verified BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, password_changed_at DATETIME DEFAULT NULL, timezone VARCHAR(100) DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL, failed_login_attempts SMALLINT DEFAULT 0 NOT NULL, locked_until DATETIME DEFAULT NULL, avatar VARCHAR(64) DEFAULT NULL)');
        $this->addSql('INSERT INTO users (id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_verified, created_at, updated_at, last_login_at, password_changed_at, timezone, locale, failed_login_attempts, locked_until, avatar) SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_verified, created_at, updated_at, last_login_at, password_changed_at, timezone, locale, failed_login_attempts, locked_until, avatar FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON users (email)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__messenger_messages AS SELECT id, body, headers, queue_name, created_at, available_at, delivered_at FROM messenger_messages');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO messenger_messages (id, body, headers, queue_name, created_at, available_at, delivered_at) SELECT id, body, headers, queue_name, created_at, available_at, delivered_at FROM __temp__messenger_messages');
        $this->addSql('DROP TABLE __temp__messenger_messages');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__messenger_messages AS SELECT id, body, headers, queue_name, created_at, available_at, delivered_at FROM messenger_messages');
        $this->addSql('DROP TABLE messenger_messages');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO messenger_messages (id, body, headers, queue_name, created_at, available_at, delivered_at) SELECT id, body, headers, queue_name, created_at, available_at, delivered_at FROM __temp__messenger_messages');
        $this->addSql('DROP TABLE __temp__messenger_messages');
        $this->addSql('CREATE TEMPORARY TABLE __temp__task AS SELECT id, name, description, is_done, created_at, update_at FROM task');
        $this->addSql('DROP TABLE task');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB NOT NULL, is_done BOOLEAN NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL)');
        $this->addSql('INSERT INTO task (id, name, description, is_done, created_at, update_at) SELECT id, name, description, is_done, created_at, update_at FROM __temp__task');
        $this->addSql('DROP TABLE __temp__task');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_verified, created_at, updated_at, last_login_at, password_changed_at, timezone, locale, failed_login_attempts, locked_until, avatar FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, position VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, notes CLOB DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, is_verified BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, password_changed_at DATETIME DEFAULT NULL, timezone VARCHAR(100) DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL, failed_login_attempts SMALLINT DEFAULT 0 NOT NULL, locked_until DATETIME DEFAULT NULL, avatar VARCHAR(64) DEFAULT NULL)');
        $this->addSql('INSERT INTO users (id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_verified, created_at, updated_at, last_login_at, password_changed_at, timezone, locale, failed_login_attempts, locked_until, avatar) SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_verified, created_at, updated_at, last_login_at, password_changed_at, timezone, locale, failed_login_attempts, locked_until, avatar FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
    }
}
