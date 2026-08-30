<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260829200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create general_settings table for platform-wide configuration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE general_settings (id INT AUTO_INCREMENT NOT NULL, show_hidden_users TINYINT DEFAULT 1 NOT NULL, enabled_languages JSON NOT NULL, website_default_language VARCHAR(5) NOT NULL, last_updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE general_settings');
    }
}
