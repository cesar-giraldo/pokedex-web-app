<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260918120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add non-enumerable public_token to pokemon_image';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM pokemon_image');
        $this->addSql('ALTER TABLE pokemon_image ADD public_token VARCHAR(22) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_pokemon_image_public_token ON pokemon_image (public_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_pokemon_image_public_token');
        $this->addSql('ALTER TABLE pokemon_image DROP public_token');
    }
}
