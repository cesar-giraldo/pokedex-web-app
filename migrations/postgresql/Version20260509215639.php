<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509215639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon ALTER height TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE pokemon ALTER weight TYPE INT');
        $this->addSql('ALTER TABLE pokemon ALTER list_order TYPE INT');
        $this->addSql('ALTER TABLE pokemon ALTER attack TYPE INT');
        $this->addSql('ALTER TABLE pokemon ALTER defense TYPE INT');
        $this->addSql('ALTER TABLE pokemon ALTER speed TYPE INT');
        $this->addSql('ALTER TABLE pokemon ALTER health_points TYPE INT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon ALTER height TYPE NUMERIC(10, 2)');
        $this->addSql('ALTER TABLE pokemon ALTER weight TYPE NUMERIC(10, 0)');
        $this->addSql('ALTER TABLE pokemon ALTER list_order TYPE NUMERIC(10, 0)');
        $this->addSql('ALTER TABLE pokemon ALTER attack TYPE NUMERIC(10, 0)');
        $this->addSql('ALTER TABLE pokemon ALTER defense TYPE NUMERIC(10, 0)');
        $this->addSql('ALTER TABLE pokemon ALTER speed TYPE NUMERIC(10, 0)');
        $this->addSql('ALTER TABLE pokemon ALTER health_points TYPE NUMERIC(10, 0)');
    }
}
