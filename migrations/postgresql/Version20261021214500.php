<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021214500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record whether the user has chosen a platform password';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD password_chosen BOOLEAN DEFAULT true NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP password_chosen');
    }
}
