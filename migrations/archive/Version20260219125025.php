<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219125025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Skip if indexes already exist (SQLite specific)
        $this->addSql('CREATE TEMPORARY TABLE __temp__knowledge_base_articles AS SELECT id, title, content, summary, created_at, updated_at, status, view_count, like_count, dislike_count, meta_description, slug, author_id, parent_article_id FROM knowledge_base_articles');
        $this->addSql('DROP TABLE knowledge_base_articles');
        $this->addSql('CREATE TABLE knowledge_base_articles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, summary CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, status VARCHAR(50) NOT NULL, view_count INTEGER NOT NULL, like_count INTEGER DEFAULT NULL, dislike_count INTEGER DEFAULT NULL, meta_description CLOB DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, author_id INTEGER NOT NULL, parent_article_id INTEGER DEFAULT NULL, CONSTRAINT FK_3D4FEA11F675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3D4FEA1135D702CC FOREIGN KEY (parent_article_id) REFERENCES knowledge_base_articles (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO knowledge_base_articles (id, title, content, summary, created_at, updated_at, status, view_count, like_count, dislike_count, meta_description, slug, author_id, parent_article_id) SELECT id, title, content, summary, created_at, updated_at, status, view_count, like_count, dislike_count, meta_description, slug, author_id, parent_article_id FROM __temp__knowledge_base_articles');
        $this->addSql('DROP TABLE __temp__knowledge_base_articles');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_3D4FEA1135D702CC ON knowledge_base_articles (parent_article_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_created ON knowledge_base_articles (created_at)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_slug ON knowledge_base_articles (slug)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_author ON knowledge_base_articles (author_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_status ON knowledge_base_articles (status)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__resource_allocations AS SELECT id, date, hours, status, notes, created_at, updated_at, resource_id, task_id FROM resource_allocations');
        $this->addSql('DROP TABLE resource_allocations');
        $this->addSql('CREATE TABLE resource_allocations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, hours NUMERIC(5, 2) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, resource_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_C4527CAC89329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C4527CAC8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO resource_allocations (id, date, hours, status, notes, created_at, updated_at, resource_id, task_id) SELECT id, date, hours, status, notes, created_at, updated_at, resource_id, task_id FROM __temp__resource_allocations');
        $this->addSql('DROP TABLE __temp__resource_allocations');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_task ON resource_allocations (task_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_date ON resource_allocations (date)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_resource_date ON resource_allocations (resource_id, date)');
        $this->addSql('CREATE INDEX IF NOT EXISTS IDX_C4527CAC89329D25 ON resource_allocations (resource_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_status ON resource_allocations (status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__knowledge_base_articles AS SELECT id, title, content, summary, created_at, updated_at, status, view_count, like_count, dislike_count, meta_description, slug, author_id, parent_article_id FROM knowledge_base_articles');
        $this->addSql('DROP TABLE knowledge_base_articles');
        $this->addSql('CREATE TABLE knowledge_base_articles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, summary CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, status VARCHAR(50) NOT NULL, view_count INTEGER NOT NULL, like_count INTEGER DEFAULT NULL, dislike_count INTEGER DEFAULT NULL, meta_description CLOB DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, author_id INTEGER NOT NULL, parent_article_id INTEGER DEFAULT NULL, CONSTRAINT FK_3D4FEA11F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3D4FEA1135D702CC FOREIGN KEY (parent_article_id) REFERENCES knowledge_base_articles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO knowledge_base_articles (id, title, content, summary, created_at, updated_at, status, view_count, like_count, dislike_count, meta_description, slug, author_id, parent_article_id) SELECT id, title, content, summary, created_at, updated_at, status, view_count, like_count, dislike_count, meta_description, slug, author_id, parent_article_id FROM __temp__knowledge_base_articles');
        $this->addSql('DROP TABLE __temp__knowledge_base_articles');
        $this->addSql('CREATE INDEX IDX_3D4FEA1135D702CC ON knowledge_base_articles (parent_article_id)');
        $this->addSql('CREATE INDEX idx_author ON knowledge_base_articles (author_id)');
        $this->addSql('CREATE INDEX idx_created ON knowledge_base_articles (created_at)');
        $this->addSql('CREATE INDEX idx_slug ON knowledge_base_articles (slug)');
        $this->addSql('CREATE INDEX idx_article_status ON knowledge_base_articles (status)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__resource_allocations AS SELECT id, date, hours, status, notes, created_at, updated_at, resource_id, task_id FROM resource_allocations');
        $this->addSql('DROP TABLE resource_allocations');
        $this->addSql('CREATE TABLE resource_allocations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, hours NUMERIC(5, 2) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, resource_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_C4527CAC89329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C4527CAC8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO resource_allocations (id, date, hours, status, notes, created_at, updated_at, resource_id, task_id) SELECT id, date, hours, status, notes, created_at, updated_at, resource_id, task_id FROM __temp__resource_allocations');
        $this->addSql('DROP TABLE __temp__resource_allocations');
        $this->addSql('CREATE INDEX IDX_C4527CAC89329D25 ON resource_allocations (resource_id)');
        $this->addSql('CREATE INDEX idx_resource_date ON resource_allocations (resource_id, date)');
        $this->addSql('CREATE INDEX idx_task ON resource_allocations (task_id)');
        $this->addSql('CREATE INDEX idx_date ON resource_allocations (date)');
        $this->addSql('CREATE INDEX idx_allocation_status ON resource_allocations (status)');
    }
}
