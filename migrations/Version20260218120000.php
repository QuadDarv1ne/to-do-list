<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CRM tables: clients, deals, deal_history, client_interactions';
    }

    public function up(Schema $schema): void
    {
        // Create clients table
        $this->addSql('CREATE TABLE clients (
            id SERIAL PRIMARY KEY,
            company_name VARCHAR(255) NOT NULL,
            inn VARCHAR(12),
            kpp VARCHAR(9),
            contact_person VARCHAR(255),
            phone VARCHAR(20),
            email VARCHAR(180),
            address TEXT,
            segment VARCHAR(50) NOT NULL DEFAULT \'retail\',
            category VARCHAR(50) NOT NULL DEFAULT \'new\',
            manager_id INT,
            notes TEXT,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP,
            last_contact_at TIMESTAMP,
            CONSTRAINT fk_clients_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
        )');
        
        $this->addSql('CREATE INDEX idx_clients_segment ON clients(segment)');
        $this->addSql('CREATE INDEX idx_clients_category ON clients(category)');
        $this->addSql('CREATE INDEX idx_clients_created_at ON clients(created_at)');

        // Create deals table
        $this->addSql('CREATE TABLE deals (
            id SERIAL PRIMARY KEY,
            client_id INT NOT NULL,
            manager_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            amount DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            stage VARCHAR(50) NOT NULL DEFAULT \'lead\',
            status VARCHAR(50) NOT NULL DEFAULT \'in_progress\',
            description TEXT,
            lost_reason TEXT,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP,
            expected_close_date DATE,
            actual_close_date DATE,
            CONSTRAINT fk_deals_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            CONSTRAINT fk_deals_manager FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
        )');
        
        $this->addSql('CREATE INDEX idx_deals_status ON deals(status)');
        $this->addSql('CREATE INDEX idx_deals_stage ON deals(stage)');
        $this->addSql('CREATE INDEX idx_deals_created_at ON deals(created_at)');
        $this->addSql('CREATE INDEX idx_deals_expected_close ON deals(expected_close_date)');

        // Create deal_history table
        $this->addSql('CREATE TABLE deal_history (
            id SERIAL PRIMARY KEY,
            deal_id INT NOT NULL,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            old_value JSON,
            new_value JSON,
            created_at TIMESTAMP NOT NULL,
            CONSTRAINT fk_deal_history_deal FOREIGN KEY (deal_id) REFERENCES deals(id) ON DELETE CASCADE,
            CONSTRAINT fk_deal_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )');
        
        $this->addSql('CREATE INDEX idx_deal_history_created_at ON deal_history(created_at)');

        // Create client_interactions table
        $this->addSql('CREATE TABLE client_interactions (
            id SERIAL PRIMARY KEY,
            client_id INT NOT NULL,
            user_id INT NOT NULL,
            interaction_type VARCHAR(50) NOT NULL DEFAULT \'call\',
            interaction_date TIMESTAMP NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP NOT NULL,
            CONSTRAINT fk_client_interactions_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
            CONSTRAINT fk_client_interactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');
        
        $this->addSql('CREATE INDEX idx_client_interactions_type ON client_interactions(interaction_type)');
        $this->addSql('CREATE INDEX idx_client_interactions_date ON client_interactions(interaction_date)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS client_interactions');
        $this->addSql('DROP TABLE IF EXISTS deal_history');
        $this->addSql('DROP TABLE IF EXISTS deals');
        $this->addSql('DROP TABLE IF EXISTS clients');
    }
}
