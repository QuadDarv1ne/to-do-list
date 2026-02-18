<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Budget and Document entities';
    }

    public function up(Schema $schema): void
    {
        // Create budgets table
        $this->addSql('CREATE TABLE budgets (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            name VARCHAR(255) NOT NULL, 
            total_amount NUMERIC(10, 2) NOT NULL, 
            spent_amount NUMERIC(10, 2) NOT NULL, 
            start_date DATE NOT NULL, 
            end_date DATE DEFAULT NULL, 
            created_by INTEGER NOT NULL, 
            created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , 
            updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
            , 
            status VARCHAR(50) NOT NULL, 
            currency VARCHAR(10) DEFAULT \'RUB\'
        )');

        // Create documents table
        $this->addSql('CREATE TABLE documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, 
            title VARCHAR(255) NOT NULL, 
            content CLOB NOT NULL, 
            file_name VARCHAR(255) DEFAULT NULL, 
            content_type VARCHAR(50) NOT NULL, 
            created_by INTEGER NOT NULL, 
            created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
            , 
            updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
            , 
            version INTEGER NOT NULL, 
            status VARCHAR(50) NOT NULL, 
            description CLOB DEFAULT NULL, 
            parent_id INTEGER DEFAULT NULL, 
            tags CLOB DEFAULT NULL
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE budgets');
        $this->addSql('DROP TABLE documents');
    }
}