<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create event_store table for Event Sourcing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_store (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            event_name VARCHAR(255) NOT NULL,
            event_data TEXT NOT NULL,
            occurred_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL
        )');
        
        $this->addSql('CREATE INDEX idx_event_store_event_name ON event_store (event_name)');
        $this->addSql('CREATE INDEX idx_event_store_occurred_at ON event_store (occurred_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_store');
    }
}
