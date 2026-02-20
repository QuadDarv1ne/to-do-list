<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220162736 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE notification_preferences (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email_settings CLOB DEFAULT \'[]\' NOT NULL, push_settings CLOB DEFAULT \'[]\' NOT NULL, in_app_settings CLOB DEFAULT \'[]\' NOT NULL, quiet_hours CLOB DEFAULT NULL, frequency CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_3CAA95B4A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3CAA95B4A76ED395 ON notification_preferences (user_id)');
        $this->addSql('CREATE INDEX idx_notif_pref_user ON notification_preferences (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE notification_preferences');
    }
}
