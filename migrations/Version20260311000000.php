<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add audit_logs table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_logs (
            id INT AUTO_INCREMENT NOT NULL, 
            entity_class VARCHAR(255) NOT NULL, 
            entity_id VARCHAR(255) NOT NULL, 
            action VARCHAR(50) NOT NULL, 
            changes LONGDEFAULT NULL COMMENT \'(DC2Type:json)\', 
            old_values LONGDEFAULT NULL COMMENT \'(DC2Type:json)\', 
            new_values LONGDEFAULT NULL COMMENT \'(DC2Type:json)\', 
            user_id INT DEFAULT NULL, 
            user_name VARCHAR(255) DEFAULT NULL, 
            user_email VARCHAR(255) DEFAULT NULL, 
            ip_address VARCHAR(45) DEFAULT NULL, 
            user_agent VARCHAR(255) DEFAULT NULL, 
            reason LONGDEFAULT NULL, 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            INDEX idx_audit_user (user_id), 
            INDEX idx_audit_entity (entity_class, entity_id), 
            INDEX idx_audit_action (action), 
            INDEX idx_audit_created (created_at), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE audit_logs ADD CONSTRAINT FK_AUDIT_USER FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE audit_logs DROP FOREIGN KEY FK_AUDIT_USER');
        $this->addSql('DROP TABLE audit_logs');
    }
}
