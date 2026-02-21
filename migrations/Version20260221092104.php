<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260221092104 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_dashboard_layouts (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, widgets CLOB DEFAULT NULL, theme VARCHAR(20) DEFAULT \'light\' NOT NULL, compact_mode BOOLEAN DEFAULT 0 NOT NULL, show_empty_widgets BOOLEAN DEFAULT 1 NOT NULL, columns INTEGER DEFAULT 2 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_1CCB1F32A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_user_dashboard_user ON user_dashboard_layouts (user_id)');
        $this->addSql('CREATE INDEX idx_user_dashboard_theme ON user_dashboard_layouts (theme)');
        $this->addSql('CREATE UNIQUE INDEX user_layout_unique ON user_dashboard_layouts (user_id)');
        $this->addSql('CREATE TABLE user_preferences (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, preference_key VARCHAR(100) NOT NULL, preference_value CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_402A6F60A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_user_preferences_user ON user_preferences (user_id)');
        $this->addSql('CREATE INDEX idx_user_preferences_key ON user_preferences (preference_key)');
        $this->addSql('CREATE UNIQUE INDEX user_preference_unique ON user_preferences (user_id, preference_key)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE user_dashboard_layouts');
        $this->addSql('DROP TABLE user_preferences');
    }
}
