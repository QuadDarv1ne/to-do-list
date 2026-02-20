<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add progress field (0-100) to tasks table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tasks ADD COLUMN progress INTEGER NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // SQLite does not support DROP COLUMN reliably; field is left in place on rollback
    }
}
