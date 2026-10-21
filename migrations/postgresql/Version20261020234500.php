<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable preferred_language column to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD preferred_language VARCHAR(2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP preferred_language');
    }
}
