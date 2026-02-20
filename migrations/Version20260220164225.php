<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220164225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_devices (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, device_token VARCHAR(500) NOT NULL, platform VARCHAR(50) NOT NULL, app_version VARCHAR(50) DEFAULT NULL, device_name VARCHAR(100) DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, last_used_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_490A5090A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_user_device_user ON user_devices (user_id)');
        $this->addSql('CREATE INDEX idx_user_device_token ON user_devices (device_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE user_devices');
    }
}
