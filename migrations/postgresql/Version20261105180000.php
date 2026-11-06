<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add internal additional notes on users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD additional_notes TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP additional_notes');
    }
}
