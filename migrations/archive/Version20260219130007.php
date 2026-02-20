<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219130007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__resource_allocations AS SELECT id, date, hours, status, notes, created_at, updated_at, resource_id, task_id FROM resource_allocations');
        $this->addSql('DROP TABLE resource_allocations');
        $this->addSql('CREATE TABLE resource_allocations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, hours NUMERIC(5, 2) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, resource_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_C4527CAC89329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C4527CAC8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO resource_allocations (id, date, hours, status, notes, created_at, updated_at, resource_id, task_id) SELECT id, date, hours, status, notes, created_at, updated_at, resource_id, task_id FROM __temp__resource_allocations');
        $this->addSql('DROP TABLE __temp__resource_allocations');
        $this->addSql('CREATE INDEX IDX_C4527CAC89329D25 ON resource_allocations (resource_id)');
        $this->addSql('CREATE INDEX idx_resource_date ON resource_allocations (resource_id, date)');
        $this->addSql('CREATE INDEX idx_date ON resource_allocations (date)');
        $this->addSql('CREATE INDEX idx_task ON resource_allocations (task_id)');
        $this->addSql('CREATE INDEX idx_status ON resource_allocations (status)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__resource_allocations AS SELECT id, date, hours, status, notes, created_at, updated_at, resource_id, task_id FROM resource_allocations');
        $this->addSql('DROP TABLE resource_allocations');
        $this->addSql('CREATE TABLE resource_allocations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, hours NUMERIC(5, 2) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, resource_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_C4527CAC89329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C4527CAC8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO resource_allocations (id, date, hours, status, notes, created_at, updated_at, resource_id, task_id) SELECT id, date, hours, status, notes, created_at, updated_at, resource_id, task_id FROM __temp__resource_allocations');
        $this->addSql('DROP TABLE __temp__resource_allocations');
        $this->addSql('CREATE INDEX IDX_C4527CAC89329D25 ON resource_allocations (resource_id)');
        $this->addSql('CREATE INDEX idx_resource_date ON resource_allocations (resource_id, date)');
        $this->addSql('CREATE INDEX idx_task ON resource_allocations (task_id)');
        $this->addSql('CREATE INDEX idx_date ON resource_allocations (date)');
    }
}
