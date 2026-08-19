<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260818200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make users.email nullable for optional email addresses';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER email DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ALTER email SET NOT NULL');
    }
}
