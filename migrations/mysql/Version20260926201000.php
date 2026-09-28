<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260926201000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform branding and support email columns to general_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE general_settings ADD platform_name VARCHAR(120) DEFAULT NULL, ADD platform_slogan VARCHAR(255) DEFAULT NULL, ADD platform_logo VARCHAR(512) DEFAULT NULL, ADD platform_logo_dark VARCHAR(512) DEFAULT NULL, ADD platform_icon VARCHAR(512) DEFAULT NULL, ADD contact_support_email VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE general_settings DROP platform_name, DROP platform_slogan, DROP platform_logo, DROP platform_logo_dark, DROP platform_icon, DROP contact_support_email');
    }
}
