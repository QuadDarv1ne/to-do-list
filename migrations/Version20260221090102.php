<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221090102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_integrations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, integration_type VARCHAR(50) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, access_token VARCHAR(255) DEFAULT NULL, refresh_token VARCHAR(255) DEFAULT NULL, token_expires_at DATETIME DEFAULT NULL, metadata CLOB DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_sync_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_FCE210A8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_user_integrations_user ON user_integrations (user_id)');
        $this->addSql('CREATE INDEX idx_user_integrations_type ON user_integrations (integration_type)');
        $this->addSql('CREATE INDEX idx_user_integrations_active ON user_integrations (is_active)');
        $this->addSql('CREATE UNIQUE INDEX user_integration_unique ON user_integrations (user_id, integration_type)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE user_integrations');
    }
}
