<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260218142401 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE task_automation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, "trigger" VARCHAR(50) NOT NULL, conditions CLOB NOT NULL, actions CLOB NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, last_executed_at DATETIME DEFAULT NULL, execution_count INTEGER NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_D560D741B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D560D741B03A8386 ON task_automation (created_by_id)');
        $this->addSql('CREATE TABLE task_history (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" VARCHAR(50) NOT NULL, field VARCHAR(100) DEFAULT NULL, old_value CLOB DEFAULT NULL, new_value CLOB DEFAULT NULL, created_at DATETIME NOT NULL, metadata CLOB DEFAULT NULL, task_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_385B5AA18DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_385B5AA1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_385B5AA18DB60186 ON task_history (task_id)');
        $this->addSql('CREATE INDEX IDX_385B5AA1A76ED395 ON task_history (user_id)');
        $this->addSql('CREATE INDEX idx_task_history_task_date ON task_history (task_id, created_at)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__budgets AS SELECT id, name, total_amount, spent_amount, start_date, end_date, created_by, created_at, updated_at, status, currency FROM budgets');
        $this->addSql('DROP TABLE budgets');
        $this->addSql('CREATE TABLE budgets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, spent_amount NUMERIC(10, 2) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, created_by INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, status VARCHAR(20) NOT NULL, currency VARCHAR(10) DEFAULT \'USD\' NOT NULL, description VARCHAR(255) DEFAULT NULL)');
        $this->addSql('INSERT INTO budgets (id, name, total_amount, spent_amount, start_date, end_date, created_by, created_at, updated_at, status, currency) SELECT id, name, total_amount, spent_amount, start_date, end_date, created_by, created_at, updated_at, status, currency FROM __temp__budgets');
        $this->addSql('DROP TABLE __temp__budgets');
        $this->addSql('CREATE TEMPORARY TABLE __temp__client_interactions AS SELECT id, client_id, user_id, interaction_type, interaction_date, description, created_at FROM client_interactions');
        $this->addSql('DROP TABLE client_interactions');
        $this->addSql('CREATE TABLE client_interactions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, user_id INTEGER NOT NULL, interaction_type VARCHAR(50) NOT NULL, interaction_date DATETIME NOT NULL, description CLOB NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_E281D6BC19EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E281D6BCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO client_interactions (id, client_id, user_id, interaction_type, interaction_date, description, created_at) SELECT id, client_id, user_id, interaction_type, interaction_date, description, created_at FROM __temp__client_interactions');
        $this->addSql('DROP TABLE __temp__client_interactions');
        $this->addSql('CREATE INDEX idx_client_interactions_date ON client_interactions (interaction_date)');
        $this->addSql('CREATE INDEX idx_client_interactions_type ON client_interactions (interaction_type)');
        $this->addSql('CREATE INDEX IDX_E281D6BC19EB6921 ON client_interactions (client_id)');
        $this->addSql('CREATE INDEX IDX_E281D6BCA76ED395 ON client_interactions (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__clients AS SELECT id, company_name, inn, kpp, contact_person, phone, email, address, segment, category, manager_id, notes, created_at, updated_at, last_contact_at FROM clients');
        $this->addSql('DROP TABLE clients');
        $this->addSql('CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, company_name VARCHAR(255) NOT NULL, inn VARCHAR(12) DEFAULT NULL, kpp VARCHAR(9) DEFAULT NULL, contact_person VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, address CLOB DEFAULT NULL, segment VARCHAR(50) NOT NULL, category VARCHAR(50) NOT NULL, manager_id INTEGER DEFAULT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_contact_at DATETIME DEFAULT NULL, CONSTRAINT FK_C82E74783E3463 FOREIGN KEY (manager_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO clients (id, company_name, inn, kpp, contact_person, phone, email, address, segment, category, manager_id, notes, created_at, updated_at, last_contact_at) SELECT id, company_name, inn, kpp, contact_person, phone, email, address, segment, category, manager_id, notes, created_at, updated_at, last_contact_at FROM __temp__clients');
        $this->addSql('DROP TABLE __temp__clients');
        $this->addSql('CREATE INDEX idx_clients_created_at ON clients (created_at)');
        $this->addSql('CREATE INDEX idx_clients_category ON clients (category)');
        $this->addSql('CREATE INDEX idx_clients_segment ON clients (segment)');
        $this->addSql('CREATE INDEX IDX_C82E74783E3463 ON clients (manager_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__deal_history AS SELECT id, deal_id, user_id, "action", description, old_value, new_value, created_at FROM deal_history');
        $this->addSql('DROP TABLE deal_history');
        $this->addSql('CREATE TABLE deal_history (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, deal_id INTEGER NOT NULL, user_id INTEGER DEFAULT NULL, "action" VARCHAR(100) NOT NULL, description CLOB NOT NULL, old_value CLOB DEFAULT NULL, new_value CLOB DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_C3A0F8C3F60E2305 FOREIGN KEY (deal_id) REFERENCES deals (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C3A0F8C3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO deal_history (id, deal_id, user_id, "action", description, old_value, new_value, created_at) SELECT id, deal_id, user_id, "action", description, old_value, new_value, created_at FROM __temp__deal_history');
        $this->addSql('DROP TABLE __temp__deal_history');
        $this->addSql('CREATE INDEX idx_deal_history_created_at ON deal_history (created_at)');
        $this->addSql('CREATE INDEX IDX_C3A0F8C3F60E2305 ON deal_history (deal_id)');
        $this->addSql('CREATE INDEX IDX_C3A0F8C3A76ED395 ON deal_history (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__deals AS SELECT id, client_id, manager_id, title, amount, stage, status, description, lost_reason, created_at, updated_at, expected_close_date, actual_close_date FROM deals');
        $this->addSql('DROP TABLE deals');
        $this->addSql('CREATE TABLE deals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, client_id INTEGER NOT NULL, manager_id INTEGER NOT NULL, title VARCHAR(255) NOT NULL, amount NUMERIC(15, 2) NOT NULL, stage VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, description CLOB DEFAULT NULL, lost_reason CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, expected_close_date DATE DEFAULT NULL, actual_close_date DATE DEFAULT NULL, CONSTRAINT FK_EF39849B19EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EF39849B783E3463 FOREIGN KEY (manager_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO deals (id, client_id, manager_id, title, amount, stage, status, description, lost_reason, created_at, updated_at, expected_close_date, actual_close_date) SELECT id, client_id, manager_id, title, amount, stage, status, description, lost_reason, created_at, updated_at, expected_close_date, actual_close_date FROM __temp__deals');
        $this->addSql('DROP TABLE __temp__deals');
        $this->addSql('CREATE INDEX idx_deals_expected_close ON deals (expected_close_date)');
        $this->addSql('CREATE INDEX idx_deals_created_at ON deals (created_at)');
        $this->addSql('CREATE INDEX idx_deals_stage ON deals (stage)');
        $this->addSql('CREATE INDEX idx_deals_status ON deals (status)');
        $this->addSql('CREATE INDEX IDX_EF39849B19EB6921 ON deals (client_id)');
        $this->addSql('CREATE INDEX IDX_EF39849B783E3463 ON deals (manager_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__documents AS SELECT id, title, content, file_name, content_type, created_by, created_at, updated_at, version, status, description, parent_id, tags FROM documents');
        $this->addSql('DROP TABLE documents');
        $this->addSql('CREATE TABLE documents (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, file_name VARCHAR(255) DEFAULT NULL, content_type VARCHAR(50) NOT NULL, created_by INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, version INTEGER NOT NULL, status VARCHAR(50) NOT NULL, description CLOB DEFAULT NULL, parent_id INTEGER DEFAULT NULL, tags CLOB DEFAULT NULL)');
        $this->addSql('INSERT INTO documents (id, title, content, file_name, content_type, created_by, created_at, updated_at, version, status, description, parent_id, tags) SELECT id, title, content, file_name, content_type, created_by, created_at, updated_at, version, status, description, parent_id, tags FROM __temp__documents');
        $this->addSql('DROP TABLE __temp__documents');
        $this->addSql('CREATE TEMPORARY TABLE __temp__habit_logs AS SELECT id, habit_id, date, count, note, created_at FROM habit_logs');
        $this->addSql('DROP TABLE habit_logs');
        $this->addSql('CREATE TABLE habit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, habit_id INTEGER NOT NULL, date DATE NOT NULL, count INTEGER NOT NULL, note CLOB DEFAULT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_E3D9F8E6D0EAABF FOREIGN KEY (habit_id) REFERENCES habits (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO habit_logs (id, habit_id, date, count, note, created_at) SELECT id, habit_id, date, count, note, created_at FROM __temp__habit_logs');
        $this->addSql('DROP TABLE __temp__habit_logs');
        $this->addSql('CREATE UNIQUE INDEX habit_date_unique ON habit_logs (habit_id, date)');
        $this->addSql('CREATE INDEX IDX_1D791968E7AEB3B2 ON habit_logs (habit_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__habits AS SELECT id, user_id, name, description, frequency, week_days, target_count, category, icon, color, active, created_at FROM habits');
        $this->addSql('DROP TABLE habits');
        $this->addSql('CREATE TABLE habits (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, user_id INTEGER NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, frequency VARCHAR(50) NOT NULL, week_days CLOB NOT NULL, target_count INTEGER NOT NULL, category VARCHAR(50) NOT NULL, icon VARCHAR(50) NOT NULL, color VARCHAR(7) NOT NULL, active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, CONSTRAINT FK_AB90D0F0A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO habits (id, user_id, name, description, frequency, week_days, target_count, category, icon, color, active, created_at) SELECT id, user_id, name, description, frequency, week_days, target_count, category, icon, color, active, created_at FROM __temp__habits');
        $this->addSql('DROP TABLE __temp__habits');
        $this->addSql('CREATE INDEX IDX_A541213AA76ED395 ON habits (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE task_automation');
        $this->addSql('DROP TABLE task_history');
        $this->addSql('CREATE TEMPORARY TABLE __temp__budgets AS SELECT id, name, total_amount, spent_amount, created_by, start_date, end_date, status, currency, created_at, updated_at FROM budgets');
        $this->addSql('DROP TABLE budgets');
        $this->addSql('CREATE TABLE budgets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, total_amount NUMERIC(10, 2) NOT NULL, spent_amount NUMERIC(10, 2) NOT NULL, created_by INTEGER NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, status VARCHAR(50) NOT NULL, currency VARCHAR(10) DEFAULT \'RUB\', created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        )');
        $this->addSql('INSERT INTO budgets (id, name, total_amount, spent_amount, created_by, start_date, end_date, status, currency, created_at, updated_at) SELECT id, name, total_amount, spent_amount, created_by, start_date, end_date, status, currency, created_at, updated_at FROM __temp__budgets');
        $this->addSql('DROP TABLE __temp__budgets');
        $this->addSql('CREATE TEMPORARY TABLE __temp__client_interactions AS SELECT id, interaction_type, interaction_date, description, created_at, client_id, user_id FROM client_interactions');
        $this->addSql('DROP TABLE client_interactions');
        $this->addSql('CREATE TABLE client_interactions (id INTEGER DEFAULT NULL, interaction_type VARCHAR(50) DEFAULT \'call\' NOT NULL, interaction_date DATETIME NOT NULL, description CLOB NOT NULL, created_at DATETIME NOT NULL, client_id INTEGER NOT NULL, user_id INTEGER NOT NULL, PRIMARY KEY (id), CONSTRAINT fk_client_interactions_client FOREIGN KEY (client_id) REFERENCES clients (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_client_interactions_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO client_interactions (id, interaction_type, interaction_date, description, created_at, client_id, user_id) SELECT id, interaction_type, interaction_date, description, created_at, client_id, user_id FROM __temp__client_interactions');
        $this->addSql('DROP TABLE __temp__client_interactions');
        $this->addSql('CREATE INDEX IDX_E281D6BC19EB6921 ON client_interactions (client_id)');
        $this->addSql('CREATE INDEX IDX_E281D6BCA76ED395 ON client_interactions (user_id)');
        $this->addSql('CREATE INDEX idx_client_interactions_type ON client_interactions (interaction_type)');
        $this->addSql('CREATE INDEX idx_client_interactions_date ON client_interactions (interaction_date)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__clients AS SELECT id, company_name, inn, kpp, contact_person, phone, email, address, segment, category, notes, created_at, updated_at, last_contact_at, manager_id FROM clients');
        $this->addSql('DROP TABLE clients');
        $this->addSql('CREATE TABLE clients (id INTEGER DEFAULT NULL, company_name VARCHAR(255) NOT NULL, inn VARCHAR(12) DEFAULT NULL, kpp VARCHAR(9) DEFAULT NULL, contact_person VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, address CLOB DEFAULT NULL, segment VARCHAR(50) DEFAULT \'retail\' NOT NULL, category VARCHAR(50) DEFAULT \'new\' NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_contact_at DATETIME DEFAULT NULL, manager_id INTEGER DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT fk_clients_manager FOREIGN KEY (manager_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO clients (id, company_name, inn, kpp, contact_person, phone, email, address, segment, category, notes, created_at, updated_at, last_contact_at, manager_id) SELECT id, company_name, inn, kpp, contact_person, phone, email, address, segment, category, notes, created_at, updated_at, last_contact_at, manager_id FROM __temp__clients');
        $this->addSql('DROP TABLE __temp__clients');
        $this->addSql('CREATE INDEX IDX_C82E74783E3463 ON clients (manager_id)');
        $this->addSql('CREATE INDEX idx_clients_segment ON clients (segment)');
        $this->addSql('CREATE INDEX idx_clients_category ON clients (category)');
        $this->addSql('CREATE INDEX idx_clients_created_at ON clients (created_at)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__deal_history AS SELECT id, "action", description, old_value, new_value, created_at, deal_id, user_id FROM deal_history');
        $this->addSql('DROP TABLE deal_history');
        $this->addSql('CREATE TABLE deal_history (id INTEGER DEFAULT NULL, "action" VARCHAR(100) NOT NULL, description CLOB NOT NULL, old_value CLOB DEFAULT NULL, new_value CLOB DEFAULT NULL, created_at DATETIME NOT NULL, deal_id INTEGER NOT NULL, user_id INTEGER DEFAULT NULL, PRIMARY KEY (id), CONSTRAINT fk_deal_history_deal FOREIGN KEY (deal_id) REFERENCES deals (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_deal_history_user FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO deal_history (id, "action", description, old_value, new_value, created_at, deal_id, user_id) SELECT id, "action", description, old_value, new_value, created_at, deal_id, user_id FROM __temp__deal_history');
        $this->addSql('DROP TABLE __temp__deal_history');
        $this->addSql('CREATE INDEX IDX_C3A0F8C3F60E2305 ON deal_history (deal_id)');
        $this->addSql('CREATE INDEX IDX_C3A0F8C3A76ED395 ON deal_history (user_id)');
        $this->addSql('CREATE INDEX idx_deal_history_created_at ON deal_history (created_at)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__deals AS SELECT id, title, amount, stage, status, created_at, updated_at, expected_close_date, actual_close_date, description, lost_reason, client_id, manager_id FROM deals');
        $this->addSql('DROP TABLE deals');
        $this->addSql('CREATE TABLE deals (id INTEGER DEFAULT NULL, title VARCHAR(255) NOT NULL, amount NUMERIC(15, 2) DEFAULT \'0.00\' NOT NULL, stage VARCHAR(50) DEFAULT \'lead\' NOT NULL, status VARCHAR(50) DEFAULT \'in_progress\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, expected_close_date DATE DEFAULT NULL, actual_close_date DATE DEFAULT NULL, description CLOB DEFAULT NULL, lost_reason CLOB DEFAULT NULL, client_id INTEGER NOT NULL, manager_id INTEGER NOT NULL, PRIMARY KEY (id), CONSTRAINT fk_deals_client FOREIGN KEY (client_id) REFERENCES clients (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT fk_deals_manager FOREIGN KEY (manager_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO deals (id, title, amount, stage, status, created_at, updated_at, expected_close_date, actual_close_date, description, lost_reason, client_id, manager_id) SELECT id, title, amount, stage, status, created_at, updated_at, expected_close_date, actual_close_date, description, lost_reason, client_id, manager_id FROM __temp__deals');
        $this->addSql('DROP TABLE __temp__deals');
        $this->addSql('CREATE INDEX IDX_EF39849B19EB6921 ON deals (client_id)');
        $this->addSql('CREATE INDEX IDX_EF39849B783E3463 ON deals (manager_id)');
        $this->addSql('CREATE INDEX idx_deals_status ON deals (status)');
        $this->addSql('CREATE INDEX idx_deals_stage ON deals (stage)');
        $this->addSql('CREATE INDEX idx_deals_created_at ON deals (created_at)');
        $this->addSql('CREATE INDEX idx_deals_expected_close ON deals (expected_close_date)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__documents AS SELECT id, title, content, file_name, content_type, created_by, created_at, updated_at, version, status, description, parent_id, tags FROM documents');
        $this->addSql('DROP TABLE documents');
        $this->addSql('CREATE TABLE documents (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, file_name VARCHAR(255) DEFAULT NULL, content_type VARCHAR(50) NOT NULL, created_by INTEGER NOT NULL, created_at DATETIME NOT NULL --(DC2Type:datetime_immutable)
        , updated_at DATETIME DEFAULT NULL --(DC2Type:datetime_immutable)
        , version INTEGER NOT NULL, status VARCHAR(50) NOT NULL, description CLOB DEFAULT NULL, parent_id INTEGER DEFAULT NULL, tags CLOB DEFAULT NULL)');
        $this->addSql('INSERT INTO documents (id, title, content, file_name, content_type, created_by, created_at, updated_at, version, status, description, parent_id, tags) SELECT id, title, content, file_name, content_type, created_by, created_at, updated_at, version, status, description, parent_id, tags FROM __temp__documents');
        $this->addSql('DROP TABLE __temp__documents');
        $this->addSql('CREATE TEMPORARY TABLE __temp__habit_logs AS SELECT id, date, count, note, created_at, habit_id FROM habit_logs');
        $this->addSql('DROP TABLE habit_logs');
        $this->addSql('CREATE TABLE habit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, count INTEGER NOT NULL, note CLOB DEFAULT NULL, created_at DATETIME NOT NULL, habit_id INTEGER NOT NULL, CONSTRAINT FK_1D791968E7AEB3B2 FOREIGN KEY (habit_id) REFERENCES habits (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO habit_logs (id, date, count, note, created_at, habit_id) SELECT id, date, count, note, created_at, habit_id FROM __temp__habit_logs');
        $this->addSql('DROP TABLE __temp__habit_logs');
        $this->addSql('CREATE UNIQUE INDEX habit_date_unique ON habit_logs (habit_id, date)');
        $this->addSql('CREATE INDEX IDX_E3D9F8E6D0EAABF ON habit_logs (habit_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__habits AS SELECT id, name, description, frequency, week_days, target_count, category, icon, color, active, created_at, user_id FROM habits');
        $this->addSql('DROP TABLE habits');
        $this->addSql('CREATE TABLE habits (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, frequency VARCHAR(50) NOT NULL, week_days CLOB NOT NULL, target_count INTEGER NOT NULL, category VARCHAR(50) NOT NULL, icon VARCHAR(50) NOT NULL, color VARCHAR(7) NOT NULL, active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_A541213AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO habits (id, name, description, frequency, week_days, target_count, category, icon, color, active, created_at, user_id) SELECT id, name, description, frequency, week_days, target_count, category, icon, color, active, created_at, user_id FROM __temp__habits');
        $this->addSql('DROP TABLE __temp__habits');
        $this->addSql('CREATE INDEX IDX_AB90D0F0A76ED395 ON habits (user_id)');
    }
}
