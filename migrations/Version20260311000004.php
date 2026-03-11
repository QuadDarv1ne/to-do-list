<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email_templates table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE email_templates (
            id INT AUTO_INCREMENT NOT NULL, 
            code VARCHAR(100) NOT NULL, 
            subject VARCHAR(255) NOT NULL, 
            body_html LONGTEXT NOT NULL, 
            body_text LONGTEXT DEFAULT NULL, 
            variables LONGDEFAULT NULL COMMENT \'(DC2Type:json)\', 
            is_active TINYINT(1) DEFAULT 1 NOT NULL, 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            UNIQUE INDEX UNIQ_EMAIL_TEMPLATE_CODE (code), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE email_templates');
    }
}
