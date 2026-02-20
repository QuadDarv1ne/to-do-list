<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220165147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mentions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id INTEGER NOT NULL, content CLOB DEFAULT NULL, is_read BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, mentioned_user_id INTEGER NOT NULL, mentioned_by_user_id INTEGER NOT NULL, CONSTRAINT FK_FE39735FE6655814 FOREIGN KEY (mentioned_user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_FE39735F73CECDCA FOREIGN KEY (mentioned_by_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_FE39735FE6655814 ON mentions (mentioned_user_id)');
        $this->addSql('CREATE INDEX idx_mention_user_read ON mentions (mentioned_user_id, is_read)');
        $this->addSql('CREATE INDEX idx_mention_by_user ON mentions (mentioned_by_user_id)');
        $this->addSql('CREATE INDEX idx_mention_entity ON mentions (entity_type, entity_id)');
        $this->addSql('CREATE TABLE saved_searches (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, filters CLOB NOT NULL, columns CLOB DEFAULT NULL, is_default BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_EF93F31A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EF93F31A76ED395 ON saved_searches (user_id)');
        $this->addSql('CREATE INDEX idx_saved_search_user_default ON saved_searches (user_id, is_default)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE mentions');
        $this->addSql('DROP TABLE saved_searches');
    }
}
