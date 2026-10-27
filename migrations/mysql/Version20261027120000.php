<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add single-use password reset tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE password_reset_tokens (id INT AUTO_INCREMENT NOT NULL, selector VARCHAR(32) NOT NULL, verifier_hash VARCHAR(64) NOT NULL, channel VARCHAR(16) NOT NULL, expires_at DATETIME NOT NULL, consumed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_password_reset_tokens_selector (selector), INDEX idx_password_reset_tokens_user_channel (user_id, channel), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE password_reset_tokens ADD CONSTRAINT FK_PASSWORD_RESET_TOKENS_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE password_reset_tokens DROP FOREIGN KEY FK_PASSWORD_RESET_TOKENS_USER');
        $this->addSql('DROP TABLE password_reset_tokens');
    }
}
