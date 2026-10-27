<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027091500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user pokemon favorites';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_pokemon_favorites (id INT AUTO_INCREMENT NOT NULL, favorited_at DATETIME NOT NULL, user_id INT NOT NULL, pokemon_id INT NOT NULL, INDEX idx_user_pokemon_favorites_user_favorited (user_id, favorited_at), INDEX IDX_USER_POKEMON_FAVORITES_POKEMON (pokemon_id), UNIQUE INDEX uniq_user_pokemon_favorite (user_id, pokemon_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_pokemon_favorites ADD CONSTRAINT FK_USER_POKEMON_FAVORITES_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_pokemon_favorites ADD CONSTRAINT FK_USER_POKEMON_FAVORITES_POKEMON FOREIGN KEY (pokemon_id) REFERENCES pokemon (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_pokemon_favorites DROP FOREIGN KEY FK_USER_POKEMON_FAVORITES_USER');
        $this->addSql('ALTER TABLE user_pokemon_favorites DROP FOREIGN KEY FK_USER_POKEMON_FAVORITES_POKEMON');
        $this->addSql('DROP TABLE user_pokemon_favorites');
    }
}
