<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user relationship to tags table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tags ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE tags ADD CONSTRAINT FK_6FBC9426A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_6FBC9426A76ED395 ON tags (user_id)');
        
        // Update existing tags to assign them to a default user (assuming user ID 1 exists)
        $this->addSql('UPDATE tags SET user_id = 1 WHERE user_id IS NULL OR user_id = 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tags DROP FOREIGN KEY FK_6FBC9426A76ED395');
        $this->addSql('DROP INDEX IDX_6FBC9426A76ED395 ON tags');
        $this->addSql('ALTER TABLE tags DROP user_id');
    }
}