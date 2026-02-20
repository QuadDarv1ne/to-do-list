<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user relationship to tags table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // For SQLite, we need to recreate the table to add foreign key constraint
        $this->addSql('CREATE TEMPORARY TABLE __temp__tags_backup AS SELECT id, name, description, color, created_at, updated_at FROM tags');
        $this->addSql('DROP TABLE tags');
        $this->addSql('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description CLOB DEFAULT NULL, color VARCHAR(7) DEFAULT \'#007bff\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_6FBC9426A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO tags (id, name, description, color, created_at, updated_at, user_id) SELECT id, name, description, color, created_at, updated_at, 1 FROM __temp__tags_backup');
        $this->addSql('DROP TABLE __temp__tags_backup');
        $this->addSql('CREATE INDEX IDX_6FBC9426A76ED395 ON tags (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__tags_backup AS SELECT id, name, description, color, created_at, updated_at FROM tags');
        $this->addSql('DROP TABLE tags');
        $this->addSql('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description CLOB DEFAULT NULL, color VARCHAR(7) DEFAULT \'#007bff\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)');
        $this->addSql('INSERT INTO tags (id, name, description, color, created_at, updated_at) SELECT id, name, description, color, created_at, updated_at FROM __temp__tags_backup');
        $this->addSql('DROP TABLE __temp__tags_backup');
    }
}