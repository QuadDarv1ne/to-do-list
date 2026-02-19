<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219110801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE knowledge_base_articles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, summary CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, status VARCHAR(50) NOT NULL, view_count INTEGER NOT NULL, like_count INTEGER DEFAULT NULL, dislike_count INTEGER DEFAULT NULL, meta_description CLOB DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, author_id INTEGER NOT NULL, parent_article_id INTEGER DEFAULT NULL, CONSTRAINT FK_3D4FEA11F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3D4FEA1135D702CC FOREIGN KEY (parent_article_id) REFERENCES knowledge_base_articles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3D4FEA11F675F31B ON knowledge_base_articles (author_id)');
        $this->addSql('CREATE INDEX IDX_3D4FEA1135D702CC ON knowledge_base_articles (parent_article_id)');
        $this->addSql('CREATE TABLE knowledge_base_article_knowledge_base_category (knowledge_base_article_id INTEGER NOT NULL, knowledge_base_category_id INTEGER NOT NULL, PRIMARY KEY (knowledge_base_article_id, knowledge_base_category_id), CONSTRAINT FK_35B2D2AC9D68CDED FOREIGN KEY (knowledge_base_article_id) REFERENCES knowledge_base_articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_35B2D2AC35AB2003 FOREIGN KEY (knowledge_base_category_id) REFERENCES knowledge_base_categories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_35B2D2AC9D68CDED ON knowledge_base_article_knowledge_base_category (knowledge_base_article_id)');
        $this->addSql('CREATE INDEX IDX_35B2D2AC35AB2003 ON knowledge_base_article_knowledge_base_category (knowledge_base_category_id)');
        $this->addSql('CREATE TABLE knowledge_base_article_tag (knowledge_base_article_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY (knowledge_base_article_id, tag_id), CONSTRAINT FK_B67B29189D68CDED FOREIGN KEY (knowledge_base_article_id) REFERENCES knowledge_base_articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B67B2918BAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B67B29189D68CDED ON knowledge_base_article_tag (knowledge_base_article_id)');
        $this->addSql('CREATE INDEX IDX_B67B2918BAD26311 ON knowledge_base_article_tag (tag_id)');
        $this->addSql('CREATE TABLE knowledge_base_categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, parent_category_id INTEGER DEFAULT NULL, CONSTRAINT FK_D50F0D19796A8F92 FOREIGN KEY (parent_category_id) REFERENCES knowledge_base_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D50F0D19796A8F92 ON knowledge_base_categories (parent_category_id)');
        $this->addSql('CREATE TABLE resource_allocations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, hours NUMERIC(5, 2) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, resource_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_C4527CAC89329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C4527CAC8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C4527CAC89329D25 ON resource_allocations (resource_id)');
        $this->addSql('CREATE INDEX IDX_C4527CAC8DB60186 ON resource_allocations (task_id)');
        $this->addSql('CREATE TABLE resources (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, hourly_rate NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, capacity_per_week SMALLINT DEFAULT 40 NOT NULL, status VARCHAR(20) DEFAULT \'available\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE TABLE resource_skill (resource_id INTEGER NOT NULL, skill_id INTEGER NOT NULL, PRIMARY KEY (resource_id, skill_id), CONSTRAINT FK_75869E4589329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_75869E455585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_75869E4589329D25 ON resource_skill (resource_id)');
        $this->addSql('CREATE INDEX IDX_75869E455585C142 ON resource_skill (skill_id)');
        $this->addSql('CREATE TABLE skills (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, proficiency_level SMALLINT DEFAULT 1 NOT NULL)');
        $this->addSql('CREATE TABLE skill_task (skill_id INTEGER NOT NULL, task_id INTEGER NOT NULL, PRIMARY KEY (skill_id, task_id), CONSTRAINT FK_153F47975585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_153F47978DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_153F47975585C142 ON skill_task (skill_id)');
        $this->addSql('CREATE INDEX IDX_153F47978DB60186 ON skill_task (task_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE knowledge_base_articles');
        $this->addSql('DROP TABLE knowledge_base_article_knowledge_base_category');
        $this->addSql('DROP TABLE knowledge_base_article_tag');
        $this->addSql('DROP TABLE knowledge_base_categories');
        $this->addSql('DROP TABLE resource_allocations');
        $this->addSql('DROP TABLE resources');
        $this->addSql('DROP TABLE resource_skill');
        $this->addSql('DROP TABLE skills');
        $this->addSql('DROP TABLE skill_task');
    }
}
