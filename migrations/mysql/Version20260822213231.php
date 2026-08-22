<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260822213231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial MySQL schema for pokemon, pokemon_type, users and messenger_messages';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE pokemon (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, height INT NOT NULL, weight INT NOT NULL, list_order INT DEFAULT NULL, sprite_front VARCHAR(255) DEFAULT NULL, sprite_back VARCHAR(255) DEFAULT NULL, attack INT DEFAULT NULL, defense INT DEFAULT NULL, speed INT DEFAULT NULL, health_points INT DEFAULT NULL, created_at DATETIME DEFAULT NULL, last_updated_at DATETIME DEFAULT NULL, is_hidden TINYINT DEFAULT 0, description LONGTEXT DEFAULT NULL, type_id INT DEFAULT NULL, INDEX IDX_62DC90F3C54C8C93 (type_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pokemon_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, generation VARCHAR(50) DEFAULT NULL, sprite VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, lastname VARCHAR(100) NOT NULL, email VARCHAR(180) DEFAULT NULL, country_code INT DEFAULT NULL, cellphone VARCHAR(20) DEFAULT NULL, roles JSON NOT NULL, nickname VARCHAR(100) NOT NULL, password VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, last_updated_at DATETIME NOT NULL, no_login_until DATETIME DEFAULT NULL, failed_login_attempts INT DEFAULT 0 NOT NULL, is_hidden TINYINT DEFAULT 0, UNIQUE INDEX uniq_users_nickname (nickname), UNIQUE INDEX uniq_users_country_cellphone (country_code, cellphone), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE pokemon ADD CONSTRAINT FK_62DC90F3C54C8C93 FOREIGN KEY (type_id) REFERENCES pokemon_type (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pokemon DROP FOREIGN KEY FK_62DC90F3C54C8C93');
        $this->addSql('DROP TABLE pokemon');
        $this->addSql('DROP TABLE pokemon_type');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
