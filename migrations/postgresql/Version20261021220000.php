<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a nullable unique Google subject to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD google_sub VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_google_sub ON users (google_sub)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_users_google_sub');
        $this->addSql('ALTER TABLE users DROP google_sub');
    }
}
