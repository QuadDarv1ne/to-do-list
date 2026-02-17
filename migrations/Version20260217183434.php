<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260217183434 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size INTEGER NOT NULL, file_path VARCHAR(255) NOT NULL, uploaded_at DATETIME NOT NULL, task_id INTEGER NOT NULL, uploaded_by_id INTEGER NOT NULL, CONSTRAINT FK_1B157E48DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1B157E4A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1B157E48DB60186 ON task_attachments (task_id)');
        $this->addSql('CREATE INDEX IDX_1B157E4A2B28FE8 ON task_attachments (uploaded_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE task_attachments');
    }
}
