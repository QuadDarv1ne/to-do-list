<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add social_accounts table and update users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE social_accounts (
            id INT AUTO_INCREMENT NOT NULL, 
            user_id INT NOT NULL, 
            provider VARCHAR(50) NOT NULL, 
            provider_id VARCHAR(255) NOT NULL, 
            provider_email VARCHAR(255) NOT NULL, 
            provider_name VARCHAR(255) DEFAULT NULL, 
            provider_avatar VARCHAR(500) DEFAULT NULL, 
            provider_data LONGDEFAULT NULL COMMENT \'(DC2Type:json)\', 
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', 
            INDEX IDX_D72B8B3FA76ED395 (user_id), 
            UNIQUE INDEX UNIQ_SOCIAL_ACCOUNT (provider, provider_id), 
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        
        $this->addSql('ALTER TABLE social_accounts ADD CONSTRAINT FK_D72B8B3FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE social_accounts DROP FOREIGN KEY FK_D72B8B3FA76ED395');
        $this->addSql('DROP TABLE social_accounts');
    }
}
