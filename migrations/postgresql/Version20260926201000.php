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
        $this->addSql('ALTER TABLE general_settings ADD platform_name VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE general_settings ADD platform_slogan VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE general_settings ADD platform_logo VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE general_settings ADD platform_logo_dark VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE general_settings ADD platform_icon VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE general_settings ADD contact_support_email VARCHAR(180) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE general_settings DROP platform_name');
        $this->addSql('ALTER TABLE general_settings DROP platform_slogan');
        $this->addSql('ALTER TABLE general_settings DROP platform_logo');
        $this->addSql('ALTER TABLE general_settings DROP platform_logo_dark');
        $this->addSql('ALTER TABLE general_settings DROP platform_icon');
        $this->addSql('ALTER TABLE general_settings DROP contact_support_email');
    }
}
