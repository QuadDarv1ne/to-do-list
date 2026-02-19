<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260219134655 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE budgets (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, total_amount NUMERIC(10, 2) NOT NULL, spent_amount NUMERIC(10, 2) NOT NULL, created_by INTEGER NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, status VARCHAR(20) NOT NULL, currency VARCHAR(10) DEFAULT \'USD\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE TABLE categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(7) DEFAULT NULL)');
        $this->addSql('CREATE TABLE client_interactions (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, interaction_type VARCHAR(50) NOT NULL, interaction_date DATETIME NOT NULL, description CLOB NOT NULL, created_at DATETIME NOT NULL, client_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_E281D6BC19EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_E281D6BCA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_E281D6BC19EB6921 ON client_interactions (client_id)');
        $this->addSql('CREATE INDEX IDX_E281D6BCA76ED395 ON client_interactions (user_id)');
        $this->addSql('CREATE INDEX idx_client_interactions_type ON client_interactions (interaction_type)');
        $this->addSql('CREATE INDEX idx_client_interactions_date ON client_interactions (interaction_date)');
        $this->addSql('CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, company_name VARCHAR(255) NOT NULL, inn VARCHAR(12) DEFAULT NULL, kpp VARCHAR(9) DEFAULT NULL, contact_person VARCHAR(255) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, address CLOB DEFAULT NULL, segment VARCHAR(50) NOT NULL, category VARCHAR(50) NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_contact_at DATETIME DEFAULT NULL, manager_id INTEGER DEFAULT NULL, CONSTRAINT FK_C82E74783E3463 FOREIGN KEY (manager_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C82E74783E3463 ON clients (manager_id)');
        $this->addSql('CREATE INDEX idx_clients_segment ON clients (segment)');
        $this->addSql('CREATE INDEX idx_clients_category ON clients (category)');
        $this->addSql('CREATE INDEX idx_clients_created_at ON clients (created_at)');
        $this->addSql('CREATE TABLE deal_history (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" VARCHAR(100) NOT NULL, description CLOB NOT NULL, old_value CLOB DEFAULT NULL, new_value CLOB DEFAULT NULL, created_at DATETIME NOT NULL, deal_id INTEGER NOT NULL, user_id INTEGER DEFAULT NULL, CONSTRAINT FK_C3A0F8C3F60E2305 FOREIGN KEY (deal_id) REFERENCES deals (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C3A0F8C3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C3A0F8C3F60E2305 ON deal_history (deal_id)');
        $this->addSql('CREATE INDEX IDX_C3A0F8C3A76ED395 ON deal_history (user_id)');
        $this->addSql('CREATE INDEX idx_deal_history_created_at ON deal_history (created_at)');
        $this->addSql('CREATE TABLE deals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, amount NUMERIC(15, 2) NOT NULL, stage VARCHAR(50) NOT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, expected_close_date DATE DEFAULT NULL, actual_close_date DATE DEFAULT NULL, description CLOB DEFAULT NULL, lost_reason CLOB DEFAULT NULL, client_id INTEGER NOT NULL, manager_id INTEGER NOT NULL, CONSTRAINT FK_EF39849B19EB6921 FOREIGN KEY (client_id) REFERENCES clients (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_EF39849B783E3463 FOREIGN KEY (manager_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_EF39849B19EB6921 ON deals (client_id)');
        $this->addSql('CREATE INDEX IDX_EF39849B783E3463 ON deals (manager_id)');
        $this->addSql('CREATE INDEX idx_deals_status ON deals (status)');
        $this->addSql('CREATE INDEX idx_deals_stage ON deals (stage)');
        $this->addSql('CREATE INDEX idx_deals_created_at ON deals (created_at)');
        $this->addSql('CREATE INDEX idx_deals_expected_close ON deals (expected_close_date)');
        $this->addSql('CREATE TABLE documents (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, file_name VARCHAR(255) DEFAULT NULL, content_type VARCHAR(50) NOT NULL, created_by INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, version INTEGER NOT NULL, status VARCHAR(50) NOT NULL, description CLOB DEFAULT NULL, parent_id INTEGER DEFAULT NULL, tags CLOB DEFAULT NULL)');
        $this->addSql('CREATE TABLE goal_milestones (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, due_date DATETIME NOT NULL, completed BOOLEAN NOT NULL, completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, goal_id INTEGER NOT NULL, CONSTRAINT FK_3AA39083667D1AFE FOREIGN KEY (goal_id) REFERENCES goals (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3AA39083667D1AFE ON goal_milestones (goal_id)');
        $this->addSql('CREATE TABLE goals (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, target_value NUMERIC(5, 2) NOT NULL, current_value NUMERIC(5, 2) NOT NULL, status VARCHAR(50) NOT NULL, priority VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, owner_id INTEGER NOT NULL, CONSTRAINT FK_C7241E2F7E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C7241E2F7E3C61F9 ON goals (owner_id)');
        $this->addSql('CREATE TABLE habit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, count INTEGER NOT NULL, note CLOB DEFAULT NULL, created_at DATETIME NOT NULL, habit_id INTEGER NOT NULL, CONSTRAINT FK_1D791968E7AEB3B2 FOREIGN KEY (habit_id) REFERENCES habits (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1D791968E7AEB3B2 ON habit_logs (habit_id)');
        $this->addSql('CREATE UNIQUE INDEX habit_date_unique ON habit_logs (habit_id, date)');
        $this->addSql('CREATE TABLE habits (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, frequency VARCHAR(50) NOT NULL, week_days CLOB NOT NULL, target_count INTEGER NOT NULL, category VARCHAR(50) NOT NULL, icon VARCHAR(50) NOT NULL, color VARCHAR(7) NOT NULL, active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_A541213AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_A541213AA76ED395 ON habits (user_id)');
        $this->addSql('CREATE TABLE knowledge_base_articles (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content CLOB NOT NULL, summary CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, status VARCHAR(50) NOT NULL, view_count INTEGER NOT NULL, like_count INTEGER DEFAULT NULL, dislike_count INTEGER DEFAULT NULL, meta_description CLOB DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, author_id INTEGER NOT NULL, parent_article_id INTEGER DEFAULT NULL, CONSTRAINT FK_3D4FEA11F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_3D4FEA1135D702CC FOREIGN KEY (parent_article_id) REFERENCES knowledge_base_articles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3D4FEA1135D702CC ON knowledge_base_articles (parent_article_id)');
        $this->addSql('CREATE INDEX idx_author ON knowledge_base_articles (author_id)');
        $this->addSql('CREATE INDEX idx_status ON knowledge_base_articles (status)');
        $this->addSql('CREATE INDEX idx_created ON knowledge_base_articles (created_at)');
        $this->addSql('CREATE INDEX idx_slug ON knowledge_base_articles (slug)');
        $this->addSql('CREATE TABLE knowledge_base_article_knowledge_base_category (knowledge_base_article_id INTEGER NOT NULL, knowledge_base_category_id INTEGER NOT NULL, PRIMARY KEY (knowledge_base_article_id, knowledge_base_category_id), CONSTRAINT FK_35B2D2AC9D68CDED FOREIGN KEY (knowledge_base_article_id) REFERENCES knowledge_base_articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_35B2D2AC35AB2003 FOREIGN KEY (knowledge_base_category_id) REFERENCES knowledge_base_categories (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_35B2D2AC9D68CDED ON knowledge_base_article_knowledge_base_category (knowledge_base_article_id)');
        $this->addSql('CREATE INDEX IDX_35B2D2AC35AB2003 ON knowledge_base_article_knowledge_base_category (knowledge_base_category_id)');
        $this->addSql('CREATE TABLE knowledge_base_article_tag (knowledge_base_article_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY (knowledge_base_article_id, tag_id), CONSTRAINT FK_B67B29189D68CDED FOREIGN KEY (knowledge_base_article_id) REFERENCES knowledge_base_articles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_B67B2918BAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_B67B29189D68CDED ON knowledge_base_article_tag (knowledge_base_article_id)');
        $this->addSql('CREATE INDEX IDX_B67B2918BAD26311 ON knowledge_base_article_tag (tag_id)');
        $this->addSql('CREATE TABLE knowledge_base_categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, slug VARCHAR(255) DEFAULT NULL, sort_order INTEGER NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, parent_category_id INTEGER DEFAULT NULL, CONSTRAINT FK_D50F0D19796A8F92 FOREIGN KEY (parent_category_id) REFERENCES knowledge_base_categories (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D50F0D19796A8F92 ON knowledge_base_categories (parent_category_id)');
        $this->addSql('CREATE TABLE resource_allocations (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, date DATE NOT NULL, hours NUMERIC(5, 2) NOT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, notes CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, resource_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_C4527CAC89329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_C4527CAC8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_C4527CAC89329D25 ON resource_allocations (resource_id)');
        $this->addSql('CREATE INDEX idx_resource_date ON resource_allocations (resource_id, date)');
        $this->addSql('CREATE INDEX idx_task ON resource_allocations (task_id)');
        $this->addSql('CREATE INDEX idx_date ON resource_allocations (date)');
        $this->addSql('CREATE INDEX idx_status ON resource_allocations (status)');
        $this->addSql('CREATE TABLE resources (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, description CLOB DEFAULT NULL, hourly_rate NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL, capacity_per_week SMALLINT DEFAULT 40 NOT NULL, status VARCHAR(20) DEFAULT \'available\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE TABLE resource_skill (resource_id INTEGER NOT NULL, skill_id INTEGER NOT NULL, PRIMARY KEY (resource_id, skill_id), CONSTRAINT FK_75869E4589329D25 FOREIGN KEY (resource_id) REFERENCES resources (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_75869E455585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_75869E4589329D25 ON resource_skill (resource_id)');
        $this->addSql('CREATE INDEX IDX_75869E455585C142 ON resource_skill (skill_id)');
        $this->addSql('CREATE TABLE skills (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, proficiency_level SMALLINT DEFAULT 1 NOT NULL)');
        $this->addSql('CREATE TABLE skill_task (skill_id INTEGER NOT NULL, task_id INTEGER NOT NULL, PRIMARY KEY (skill_id, task_id), CONSTRAINT FK_153F47975585C142 FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_153F47978DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_153F47975585C142 ON skill_task (skill_id)');
        $this->addSql('CREATE INDEX IDX_153F47978DB60186 ON skill_task (task_id)');
        $this->addSql('CREATE TABLE tags (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description CLOB DEFAULT NULL, color VARCHAR(7) DEFAULT \'#007bff\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_6FBC9426A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_6FBC9426A76ED395 ON tags (user_id)');
        $this->addSql('CREATE TABLE task_attachments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, filename VARCHAR(255) NOT NULL, original_filename VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size INTEGER NOT NULL, file_path VARCHAR(255) NOT NULL, uploaded_at DATETIME NOT NULL, task_id INTEGER NOT NULL, uploaded_by_id INTEGER NOT NULL, CONSTRAINT FK_1B157E48DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1B157E4A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1B157E48DB60186 ON task_attachments (task_id)');
        $this->addSql('CREATE INDEX IDX_1B157E4A2B28FE8 ON task_attachments (uploaded_by_id)');
        $this->addSql('CREATE TABLE task_automation (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, "trigger" VARCHAR(50) NOT NULL, conditions CLOB NOT NULL, actions CLOB NOT NULL, is_active BOOLEAN NOT NULL, created_at DATETIME NOT NULL, last_executed_at DATETIME DEFAULT NULL, execution_count INTEGER NOT NULL, created_by_id INTEGER NOT NULL, CONSTRAINT FK_D560D741B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_D560D741B03A8386 ON task_automation (created_by_id)');
        $this->addSql('CREATE TABLE task_categories (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_26E00DC7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_task_categories_user_id ON task_categories (user_id)');
        $this->addSql('CREATE INDEX idx_task_categories_created_at ON task_categories (created_at)');
        $this->addSql('CREATE TABLE task_comments (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content CLOB NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, author_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_1F5E7C66F675F31B FOREIGN KEY (author_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1F5E7C668DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX idx_task_comments_task_id ON task_comments (task_id)');
        $this->addSql('CREATE INDEX idx_task_comments_author_id ON task_comments (author_id)');
        $this->addSql('CREATE INDEX idx_task_comments_created_at ON task_comments (created_at)');
        $this->addSql('CREATE TABLE task_dependencies (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(20) DEFAULT \'blocking\' NOT NULL, created_at DATETIME NOT NULL, dependent_task_id INTEGER NOT NULL, dependency_task_id INTEGER NOT NULL, CONSTRAINT FK_229E54A08447C86E FOREIGN KEY (dependent_task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_229E54A063BF1AC0 FOREIGN KEY (dependency_task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_229E54A08447C86E ON task_dependencies (dependent_task_id)');
        $this->addSql('CREATE INDEX IDX_229E54A063BF1AC0 ON task_dependencies (dependency_task_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_dependency ON task_dependencies (dependent_task_id, dependency_task_id)');
        $this->addSql('CREATE TABLE task_history (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" VARCHAR(50) NOT NULL, field VARCHAR(100) DEFAULT NULL, old_value CLOB DEFAULT NULL, new_value CLOB DEFAULT NULL, created_at DATETIME NOT NULL, metadata CLOB DEFAULT NULL, task_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_385B5AA18DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_385B5AA1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_385B5AA18DB60186 ON task_history (task_id)');
        $this->addSql('CREATE INDEX IDX_385B5AA1A76ED395 ON task_history (user_id)');
        $this->addSql('CREATE INDEX idx_task_history_task_date ON task_history (task_id, created_at)');
        $this->addSql('CREATE TABLE task_notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, type VARCHAR(100) NOT NULL, subject CLOB NOT NULL, message CLOB NOT NULL, is_sent BOOLEAN NOT NULL, is_read BOOLEAN NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, task_id INTEGER NOT NULL, recipient_id INTEGER NOT NULL, sender_id INTEGER NOT NULL, CONSTRAINT FK_25177D828DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_25177D82E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_25177D82F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_25177D828DB60186 ON task_notifications (task_id)');
        $this->addSql('CREATE INDEX IDX_25177D82E92F8F78 ON task_notifications (recipient_id)');
        $this->addSql('CREATE INDEX IDX_25177D82F624B39D ON task_notifications (sender_id)');
        $this->addSql('CREATE TABLE task_recurrences (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, frequency VARCHAR(20) NOT NULL, end_date DATE DEFAULT NULL, interval INTEGER NOT NULL, days_of_week CLOB DEFAULT NULL, days_of_month CLOB DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_generated DATE DEFAULT NULL, task_id INTEGER NOT NULL, user_id INTEGER NOT NULL, CONSTRAINT FK_110DC5958DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_110DC595A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_110DC5958DB60186 ON task_recurrences (task_id)');
        $this->addSql('CREATE INDEX IDX_110DC595A76ED395 ON task_recurrences (user_id)');
        $this->addSql('CREATE TABLE task_time_tracking (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, time_spent TIME NOT NULL, description CLOB DEFAULT NULL, date_logged DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_49EEEC81A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_49EEEC818DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_49EEEC81A76ED395 ON task_time_tracking (user_id)');
        $this->addSql('CREATE INDEX IDX_49EEEC818DB60186 ON task_time_tracking (task_id)');
        $this->addSql('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description CLOB DEFAULT NULL, status VARCHAR(20) DEFAULT \'pending\' NOT NULL, priority VARCHAR(20) DEFAULT \'medium\' NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, due_date DATETIME DEFAULT NULL, completed_at DATETIME DEFAULT NULL, user_id INTEGER NOT NULL, assigned_user_id INTEGER NOT NULL, category_id INTEGER DEFAULT NULL, CONSTRAINT FK_50586597A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_50586597ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_5058659712469DE2 FOREIGN KEY (category_id) REFERENCES task_categories (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_50586597A76ED395 ON tasks (user_id)');
        $this->addSql('CREATE INDEX IDX_50586597ADF66B1A ON tasks (assigned_user_id)');
        $this->addSql('CREATE INDEX idx_task_user_status ON tasks (user_id, status)');
        $this->addSql('CREATE INDEX idx_task_assigned_user_status ON tasks (assigned_user_id, status)');
        $this->addSql('CREATE INDEX idx_task_due_date_status ON tasks (due_date, status)');
        $this->addSql('CREATE INDEX idx_task_category ON tasks (category_id)');
        $this->addSql('CREATE INDEX idx_task_created_at ON tasks (created_at)');
        $this->addSql('CREATE INDEX idx_task_priority ON tasks (priority)');
        $this->addSql('CREATE TABLE task_tags (task_id INTEGER NOT NULL, tag_id INTEGER NOT NULL, PRIMARY KEY (task_id, tag_id), CONSTRAINT FK_1C0F005D8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_1C0F005DBAD26311 FOREIGN KEY (tag_id) REFERENCES tags (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_1C0F005D8DB60186 ON task_tags (task_id)');
        $this->addSql('CREATE INDEX IDX_1C0F005DBAD26311 ON task_tags (tag_id)');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE task');
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity_logs AS SELECT id, "action", description, created_at, user_id, task_id FROM activity_logs');
        $this->addSql('DROP TABLE activity_logs');
        $this->addSql('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" CLOB NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER DEFAULT NULL, event_type VARCHAR(20) DEFAULT NULL, CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F34B1DCE8DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity_logs (id, "action", description, created_at, user_id, task_id) SELECT id, "action", description, created_at, user_id, task_id FROM __temp__activity_logs');
        $this->addSql('DROP TABLE __temp__activity_logs');
        $this->addSql('CREATE INDEX IDX_F34B1DCE8DB60186 ON activity_logs (task_id)');
        $this->addSql('CREATE INDEX IDX_F34B1DCEA76ED395 ON activity_logs (user_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__notifications AS SELECT id, title, message, is_read, created_at, user_id, task_id FROM notifications');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, message CLOB DEFAULT NULL, is_read BOOLEAN NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER DEFAULT NULL, CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6000B0D38DB60186 FOREIGN KEY (task_id) REFERENCES tasks (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO notifications (id, title, message, is_read, created_at, user_id, task_id) SELECT id, title, message, is_read, created_at, user_id, task_id FROM __temp__notifications');
        $this->addSql('DROP TABLE __temp__notifications');
        $this->addSql('CREATE INDEX IDX_6000B0D38DB60186 ON notifications (task_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE INDEX idx_user_read_created ON notifications (user_id, is_read, created_at)');
        $this->addSql('CREATE INDEX idx_user_created ON notifications (user_id, created_at)');
        $this->addSql('CREATE INDEX idx_created_at ON notifications (created_at)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_verified, created_at, updated_at, last_login_at, password_changed_at, failed_login_attempts, locked_until, avatar FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, position VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, notes CLOB DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, is_totp_enabled BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, last_login_at DATETIME DEFAULT NULL, password_changed_at DATETIME DEFAULT NULL, failed_login_attempts INTEGER DEFAULT 0 NOT NULL, locked_until DATETIME DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, totp_secret VARCHAR(255) DEFAULT NULL, totp_secret_temp VARCHAR(255) DEFAULT NULL, totp_enabled_at DATETIME DEFAULT NULL, backup_codes CLOB DEFAULT NULL)');
        $this->addSql('INSERT INTO users (id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_totp_enabled, created_at, updated_at, last_login_at, password_changed_at, failed_login_attempts, locked_until, avatar) SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, notes, is_active, is_verified, created_at, updated_at, last_login_at, password_changed_at, failed_login_attempts, locked_until, avatar FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON users (username)');
        $this->addSql('CREATE INDEX idx_users_last_login ON users (last_login_at)');
        $this->addSql('CREATE INDEX idx_users_is_active ON users (is_active)');
        $this->addSql('CREATE INDEX idx_users_created_at ON users (created_at)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE comment (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content CLOB NOT NULL COLLATE "BINARY", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, author_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_9474526CF675F31B FOREIGN KEY (author_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_9474526C8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_9474526C8DB60186 ON comment (task_id)');
        $this->addSql('CREATE INDEX IDX_9474526CF675F31B ON comment (author_id)');
        $this->addSql('CREATE TABLE task (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, name VARCHAR(255) NOT NULL COLLATE "BINARY", description CLOB NOT NULL COLLATE "BINARY", is_done BOOLEAN NOT NULL, created_at DATETIME NOT NULL, update_at DATETIME NOT NULL, assigned_user_id INTEGER DEFAULT NULL, deadline DATE DEFAULT NULL, priority VARCHAR(20) DEFAULT \'normal\' NOT NULL COLLATE "BINARY", created_by_id INTEGER NOT NULL, CONSTRAINT FK_527EDB25ADF66B1A FOREIGN KEY (assigned_user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_527EDB25B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_527EDB25B03A8386 ON task (created_by_id)');
        $this->addSql('CREATE INDEX IDX_527EDB25ADF66B1A ON task (assigned_user_id)');
        $this->addSql('DROP TABLE budgets');
        $this->addSql('DROP TABLE categories');
        $this->addSql('DROP TABLE client_interactions');
        $this->addSql('DROP TABLE clients');
        $this->addSql('DROP TABLE deal_history');
        $this->addSql('DROP TABLE deals');
        $this->addSql('DROP TABLE documents');
        $this->addSql('DROP TABLE goal_milestones');
        $this->addSql('DROP TABLE goals');
        $this->addSql('DROP TABLE habit_logs');
        $this->addSql('DROP TABLE habits');
        $this->addSql('DROP TABLE knowledge_base_articles');
        $this->addSql('DROP TABLE knowledge_base_article_knowledge_base_category');
        $this->addSql('DROP TABLE knowledge_base_article_tag');
        $this->addSql('DROP TABLE knowledge_base_categories');
        $this->addSql('DROP TABLE resource_allocations');
        $this->addSql('DROP TABLE resources');
        $this->addSql('DROP TABLE resource_skill');
        $this->addSql('DROP TABLE skills');
        $this->addSql('DROP TABLE skill_task');
        $this->addSql('DROP TABLE tags');
        $this->addSql('DROP TABLE task_attachments');
        $this->addSql('DROP TABLE task_automation');
        $this->addSql('DROP TABLE task_categories');
        $this->addSql('DROP TABLE task_comments');
        $this->addSql('DROP TABLE task_dependencies');
        $this->addSql('DROP TABLE task_history');
        $this->addSql('DROP TABLE task_notifications');
        $this->addSql('DROP TABLE task_recurrences');
        $this->addSql('DROP TABLE task_time_tracking');
        $this->addSql('DROP TABLE tasks');
        $this->addSql('DROP TABLE task_tags');
        $this->addSql('CREATE TEMPORARY TABLE __temp__activity_logs AS SELECT id, "action", description, created_at, user_id, task_id FROM activity_logs');
        $this->addSql('DROP TABLE activity_logs');
        $this->addSql('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, "action" CLOB NOT NULL, description CLOB DEFAULT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER NOT NULL, CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_F34B1DCE8DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO activity_logs (id, "action", description, created_at, user_id, task_id) SELECT id, "action", description, created_at, user_id, task_id FROM __temp__activity_logs');
        $this->addSql('DROP TABLE __temp__activity_logs');
        $this->addSql('CREATE INDEX IDX_F34B1DCEA76ED395 ON activity_logs (user_id)');
        $this->addSql('CREATE INDEX IDX_F34B1DCE8DB60186 ON activity_logs (task_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__notifications AS SELECT id, title, message, is_read, created_at, user_id, task_id FROM notifications');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, title VARCHAR(255) NOT NULL, message CLOB DEFAULT NULL, is_read BOOLEAN NOT NULL, created_at DATETIME NOT NULL, user_id INTEGER NOT NULL, task_id INTEGER DEFAULT NULL, CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_6000B0D38DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON UPDATE NO ACTION ON DELETE NO ACTION NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO notifications (id, title, message, is_read, created_at, user_id, task_id) SELECT id, title, message, is_read, created_at, user_id, task_id FROM __temp__notifications');
        $this->addSql('DROP TABLE __temp__notifications');
        $this->addSql('CREATE INDEX IDX_6000B0D3A76ED395 ON notifications (user_id)');
        $this->addSql('CREATE INDEX IDX_6000B0D38DB60186 ON notifications (task_id)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__users AS SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, is_active, last_login_at, notes, avatar, is_totp_enabled, created_at, updated_at, password_changed_at, locked_until, failed_login_attempts FROM users');
        $this->addSql('DROP TABLE users');
        $this->addSql('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, username VARCHAR(180) NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) DEFAULT NULL, last_name VARCHAR(100) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, position VARCHAR(255) DEFAULT NULL, department VARCHAR(255) DEFAULT NULL, is_active BOOLEAN DEFAULT 1 NOT NULL, last_login_at DATETIME DEFAULT NULL, notes CLOB DEFAULT NULL, avatar VARCHAR(64) DEFAULT NULL, is_verified BOOLEAN DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, password_changed_at DATETIME DEFAULT NULL, locked_until DATETIME DEFAULT NULL, failed_login_attempts SMALLINT DEFAULT 0 NOT NULL, timezone VARCHAR(100) DEFAULT NULL, locale VARCHAR(10) DEFAULT NULL)');
        $this->addSql('INSERT INTO users (id, username, email, roles, password, first_name, last_name, phone, position, department, is_active, last_login_at, notes, avatar, is_verified, created_at, updated_at, password_changed_at, locked_until, failed_login_attempts) SELECT id, username, email, roles, password, first_name, last_name, phone, position, department, is_active, last_login_at, notes, avatar, is_totp_enabled, created_at, updated_at, password_changed_at, locked_until, failed_login_attempts FROM __temp__users');
        $this->addSql('DROP TABLE __temp__users');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_USERNAME ON users (username)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON users (email)');
    }
}
