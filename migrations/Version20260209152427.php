<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209152427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_dependencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(20) DEFAULT \'blocking\' NOT NULL, created_at DATETIME NOT NULL, dependent_task_id INTEGER NOT NULL, dependency_task_id INTEGER NOT NULL, CONSTRAINT FK_229E54A08447C86E FOREIGN KEY (dependent_task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_229E54A063BF1AC0 FOREIGN KEY (dependency_task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_229E54A08447C86E ON task_dependencies (dependent_task_id)');
        $this->addSql('CREATE INDEX IDX_229E54A063BF1AC0 ON task_dependencies (dependency_task_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_dependency ON task_dependencies (dependent_task_id, dependency_task_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE task_dependencies');
    }
}
