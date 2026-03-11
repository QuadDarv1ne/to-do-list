<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dashboard_widgets table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE dashboard_widgets (
            id INT AUTO_INCREMENT NOT NULL, 
            user_id INT NOT NULL, 
            type VARCHAR(100) NOT NULL, 
            title VARCHAR(255) NOT NULL, 
            configuration LONGDEFAULT NULL COMMENT \'(DC2Type:json)\', 
            position INT DEFAULT 0 NOT NULL, 
            is_active TINYINT(1) DEFAULT 1 NOT NULL, 
            size VARCHAR(50) DEFAULT \'col-md-6\' NOT NULL, 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            INDEX idx_dashboard_widgets_user (user_id), 
            INDEX idx_dashboard_widgets_position (position), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE dashboard_widgets ADD CONSTRAINT FK_DW_USER FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE dashboard_widgets DROP FOREIGN KEY FK_DW_USER');
        $this->addSql('DROP TABLE dashboard_widgets');
    }
}
