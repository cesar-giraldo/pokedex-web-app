<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform auth logo path to general_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE general_settings ADD platform_auth_logo VARCHAR(512) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE general_settings DROP platform_auth_logo');
    }
}
