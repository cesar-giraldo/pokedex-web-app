<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260907210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform and user timezone, locale and time format preferences';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE general_settings ADD default_timezone VARCHAR(64) DEFAULT 'America/Bogota' NOT NULL");
        $this->addSql("ALTER TABLE general_settings ADD default_locale VARCHAR(16) DEFAULT 'es-CO' NOT NULL");
        $this->addSql("ALTER TABLE general_settings ADD default_time_format VARCHAR(8) DEFAULT '12h' NOT NULL");
        $this->addSql("ALTER TABLE users ADD timezone VARCHAR(64) DEFAULT 'America/Bogota' NOT NULL");
        $this->addSql("ALTER TABLE users ADD locale VARCHAR(16) DEFAULT 'es-CO' NOT NULL");
        $this->addSql("ALTER TABLE users ADD time_format VARCHAR(8) DEFAULT '12h' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE general_settings DROP default_timezone');
        $this->addSql('ALTER TABLE general_settings DROP default_locale');
        $this->addSql('ALTER TABLE general_settings DROP default_time_format');
        $this->addSql('ALTER TABLE users DROP timezone');
        $this->addSql('ALTER TABLE users DROP locale');
        $this->addSql('ALTER TABLE users DROP time_format');
    }
}
