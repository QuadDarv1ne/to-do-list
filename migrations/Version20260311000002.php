<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260311000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deletedAt column to deals for soft delete';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE deals ADD deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE INDEX idx_deals_deleted_at ON deals (deleted_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_deals_deleted_at ON deals');
        $this->addSql('ALTER TABLE deals DROP deleted_at');
    }
}
