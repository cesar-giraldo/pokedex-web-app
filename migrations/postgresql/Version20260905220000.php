<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last login tracking columns to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD last_login_ip VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD last_failed_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP last_login_at');
        $this->addSql('ALTER TABLE users DROP last_login_ip');
        $this->addSql('ALTER TABLE users DROP last_failed_login_at');
    }
}
