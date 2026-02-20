<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260220092617 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, sku VARCHAR(100) NOT NULL, description CLOB DEFAULT NULL, category VARCHAR(100) NOT NULL, price NUMERIC(15, 2) NOT NULL, cost NUMERIC(15, 2) DEFAULT NULL, unit VARCHAR(50) DEFAULT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B3BA5A5AF9038C4 ON products (sku)');
        $this->addSql('CREATE INDEX idx_products_category ON products (category)');
        $this->addSql('CREATE INDEX idx_products_active ON products (is_active)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE products');
    }
}
