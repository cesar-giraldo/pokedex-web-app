<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account confirmation codes for public registration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE account_confirmation_codes (id INT AUTO_INCREMENT NOT NULL, code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, attempts INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_account_confirmation_codes_user (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE account_confirmation_codes ADD CONSTRAINT FK_ACCOUNT_CONFIRMATION_CODES_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE account_confirmation_codes DROP FOREIGN KEY FK_ACCOUNT_CONFIRMATION_CODES_USER');
        $this->addSql('DROP TABLE account_confirmation_codes');
    }
}
