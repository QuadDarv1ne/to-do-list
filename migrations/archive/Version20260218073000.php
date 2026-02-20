<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218073000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create habits and habit_logs tables';
    }

    public function up(Schema $schema): void
    {
        // Drop existing tables if they exist
        $this->addSql('DROP TABLE IF EXISTS habit_logs');
        $this->addSql('DROP TABLE IF EXISTS habits');
        
        // Create habits table
        $this->addSql('CREATE TABLE habits (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            user_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            frequency VARCHAR(50) NOT NULL,
            week_days TEXT NOT NULL,
            target_count INTEGER NOT NULL,
            category VARCHAR(50) NOT NULL,
            icon VARCHAR(50) NOT NULL,
            color VARCHAR(7) NOT NULL,
            active BOOLEAN NOT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT FK_AB90D0F0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX IDX_AB90D0F0A76ED395 ON habits (user_id)');
        
        // Create habit_logs table
        $this->addSql('CREATE TABLE habit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            habit_id INTEGER NOT NULL,
            date DATE NOT NULL,
            count INTEGER NOT NULL,
            note TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            CONSTRAINT FK_E3D9F8E6D0EAABF FOREIGN KEY (habit_id) REFERENCES habits (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        )');
        $this->addSql('CREATE INDEX IDX_E3D9F8E6D0EAABF ON habit_logs (habit_id)');
        $this->addSql('CREATE UNIQUE INDEX habit_date_unique ON habit_logs (habit_id, date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE habit_logs');
        $this->addSql('DROP TABLE habits');
    }
}
