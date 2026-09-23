<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260923060000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last database backup path and generation timestamp to general_settings';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE general_settings ADD last_backup_file_path VARCHAR(512) DEFAULT NULL');
        $this->addSql('ALTER TABLE general_settings ADD last_backup_generated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE general_settings DROP last_backup_file_path');
        $this->addSql('ALTER TABLE general_settings DROP last_backup_generated_at');
    }
}
