<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260815032800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users table for application authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, lastname VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, country_code INT DEFAULT NULL, cellphone VARCHAR(20) DEFAULT NULL, roles JSON NOT NULL, nickname VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_updated_at DATETIME NOT NULL, no_login_until DATETIME DEFAULT NULL, failed_login_attempts INT DEFAULT 0 NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_nickname ON users (nickname)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_country_cellphone ON users (country_code, cellphone)');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email_lower ON users ((LOWER(email)))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
