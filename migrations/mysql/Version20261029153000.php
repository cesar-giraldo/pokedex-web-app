<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add platform modules catalog';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE modules (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, slug VARCHAR(64) NOT NULL, sort_order DECIMAL(10, 2) NOT NULL, is_enabled TINYINT(1) DEFAULT 0 NOT NULL, description VARCHAR(500) DEFAULT NULL, created_at DATETIME NOT NULL, parent_id INT DEFAULT NULL, UNIQUE INDEX uniq_modules_slug (slug), INDEX idx_modules_parent_sort_order (parent_id, sort_order), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE modules ADD CONSTRAINT FK_MODULES_PARENT FOREIGN KEY (parent_id) REFERENCES modules (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE modules DROP FOREIGN KEY FK_MODULES_PARENT');
        $this->addSql('DROP TABLE modules');
    }
}
