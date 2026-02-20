<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220162413 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE filter_views (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(100) NOT NULL, filters CLOB NOT NULL, columns CLOB NOT NULL, sort CLOB DEFAULT NULL, group_by VARCHAR(50) DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, is_default BOOLEAN DEFAULT 0 NOT NULL, is_shared BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_38416F78A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_38416F78A76ED395 ON filter_views (user_id)');
        $this->addSql('CREATE INDEX idx_filter_view_user_default ON filter_views (user_id, is_default)');
        $this->addSql('CREATE INDEX idx_filter_view_shared ON filter_views (is_shared)');
        $this->addSql('CREATE TABLE filter_view_shared_users (filter_view_id INTEGER NOT NULL, user_id INTEGER NOT NULL, PRIMARY KEY (filter_view_id, user_id), CONSTRAINT FK_C6D2215AB4856FA2 FOREIGN KEY (filter_view_id) REFERENCES filter_views (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C6D2215AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C6D2215AB4856FA2 ON filter_view_shared_users (filter_view_id)');
        $this->addSql('CREATE INDEX IDX_C6D2215AA76ED395 ON filter_view_shared_users (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE filter_views');
        $this->addSql('DROP TABLE filter_view_shared_users');
    }
}
