<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208100223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__task AS SELECT id, name, description, is_done, created_at, update_at, assigned_user_id, deadline, priority FROM task');
        $this->addSql('DROP TABLE task');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB NOT NULL, is_done BOOLEAN NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL, assigned_user_id INTEGER DEFAULT NULL, deadline DATE DEFAULT NULL, priority VARCHAR(20) DEFAULT \'normal\' NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_527EDB25ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task (id, name, description, is_done, created_at, update_at, assigned_user_id, deadline, priority) SELECT id, name, description, is_done, created_at, update_at, assigned_user_id, deadline, priority FROM __temp__task');
        $this->addSql('DROP TABLE __temp__task');
        $this->addSql('CREATE INDEX IDX_527EDB25ADF66B1A ON task (assigned_user_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25B03A8386 ON task (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__task AS SELECT id, name, description, is_done, created_at, update_at, deadline, priority, assigned_user_id FROM task');
        $this->addSql('DROP TABLE task');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB NOT NULL, is_done BOOLEAN NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL, deadline DATE DEFAULT NULL, priority VARCHAR(20) DEFAULT \'normal\' NOT NULL, assigned_user_id INTEGER DEFAULT NULL, CONSTRAINT FK_527EDB25ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO task (id, name, description, is_done, created_at, update_at, deadline, priority, assigned_user_id) SELECT id, name, description, is_done, created_at, update_at, deadline, priority, assigned_user_id FROM __temp__task');
        $this->addSql('DROP TABLE __temp__task');
        $this->addSql('CREATE INDEX IDX_527EDB25ADF66B1A ON task (assigned_user_id)');
    }
}
