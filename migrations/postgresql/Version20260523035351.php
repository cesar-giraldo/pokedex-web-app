<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260523035351 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon ALTER height TYPE INT');
        $this->addSql('ALTER TABLE pokemon ALTER list_order DROP NOT NULL');
        $this->addSql('ALTER TABLE pokemon ALTER type_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon ALTER height TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE pokemon ALTER list_order SET NOT NULL');
        $this->addSql('ALTER TABLE pokemon ALTER type_id SET NOT NULL');
    }
}
