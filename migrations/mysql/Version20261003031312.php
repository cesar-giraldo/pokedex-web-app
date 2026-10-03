<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003031312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create notifications inbox table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(64) NOT NULL, title VARCHAR(180) NOT NULL, message LONGTEXT NOT NULL, action_url VARCHAR(512) DEFAULT NULL, payload JSON DEFAULT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, recipient_id INT NOT NULL, actor_id INT DEFAULT NULL, INDEX idx_notifications_recipient_read (recipient_id, read_at), INDEX idx_notifications_recipient_created (recipient_id, created_at), INDEX IDX_NOTIFICATIONS_ACTOR (actor_id), INDEX IDX_6000B0D3E92F8F78 (recipient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D310DAF24A FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3E92F8F78');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D310DAF24A');
        $this->addSql('DROP TABLE notifications');
    }
}
