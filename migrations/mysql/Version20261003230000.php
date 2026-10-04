<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261003230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create legal documents, versions, translations and user acceptances';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE legal_documents (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(32) NOT NULL, name VARCHAR(180) NOT NULL, UNIQUE INDEX uniq_legal_documents_type (type), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE legal_document_versions (id INT AUTO_INCREMENT NOT NULL, version_number INT NOT NULL, status VARCHAR(16) NOT NULL, requires_reacceptance TINYINT(1) NOT NULL, published_at DATETIME DEFAULT NULL, notification_title VARCHAR(180) DEFAULT NULL, notification_message LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, document_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX idx_legal_document_versions_status (document_id, status), INDEX IDX_LEGAL_DOCUMENT_VERSIONS_CREATED_BY (created_by_id), UNIQUE INDEX uniq_legal_document_version_number (document_id, version_number), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE legal_document_versions ADD CONSTRAINT FK_LEGAL_DOCUMENT_VERSIONS_DOCUMENT FOREIGN KEY (document_id) REFERENCES legal_documents (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE legal_document_versions ADD CONSTRAINT FK_LEGAL_DOCUMENT_VERSIONS_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE TABLE legal_document_version_translations (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(5) NOT NULL, title VARCHAR(180) NOT NULL, content_html LONGTEXT NOT NULL, acceptance_label VARCHAR(255) NOT NULL, summary LONGTEXT DEFAULT NULL, content_hash VARCHAR(64) DEFAULT NULL, version_id INT NOT NULL, UNIQUE INDEX uniq_legal_translation_version_language (version_id, language), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE legal_document_version_translations ADD CONSTRAINT FK_LEGAL_TRANSLATIONS_VERSION FOREIGN KEY (version_id) REFERENCES legal_document_versions (id) ON DELETE CASCADE');
        $this->addSql('CREATE TABLE user_legal_acceptances (id INT AUTO_INCREMENT NOT NULL, language VARCHAR(5) NOT NULL, accepted_at DATETIME NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(512) DEFAULT NULL, method VARCHAR(32) NOT NULL, content_hash VARCHAR(64) NOT NULL, user_id INT NOT NULL, version_id INT NOT NULL, translation_id INT NOT NULL, INDEX idx_user_legal_acceptances_user (user_id), INDEX IDX_USER_LEGAL_ACCEPTANCES_VERSION (version_id), INDEX IDX_USER_LEGAL_ACCEPTANCES_TRANSLATION (translation_id), UNIQUE INDEX uniq_user_legal_acceptance_version (user_id, version_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_legal_acceptances ADD CONSTRAINT FK_USER_LEGAL_ACCEPTANCES_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_legal_acceptances ADD CONSTRAINT FK_USER_LEGAL_ACCEPTANCES_VERSION FOREIGN KEY (version_id) REFERENCES legal_document_versions (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE user_legal_acceptances ADD CONSTRAINT FK_USER_LEGAL_ACCEPTANCES_TRANSLATION FOREIGN KEY (translation_id) REFERENCES legal_document_version_translations (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_legal_acceptances DROP FOREIGN KEY FK_USER_LEGAL_ACCEPTANCES_USER');
        $this->addSql('ALTER TABLE user_legal_acceptances DROP FOREIGN KEY FK_USER_LEGAL_ACCEPTANCES_VERSION');
        $this->addSql('ALTER TABLE user_legal_acceptances DROP FOREIGN KEY FK_USER_LEGAL_ACCEPTANCES_TRANSLATION');
        $this->addSql('DROP TABLE user_legal_acceptances');
        $this->addSql('ALTER TABLE legal_document_version_translations DROP FOREIGN KEY FK_LEGAL_TRANSLATIONS_VERSION');
        $this->addSql('DROP TABLE legal_document_version_translations');
        $this->addSql('ALTER TABLE legal_document_versions DROP FOREIGN KEY FK_LEGAL_DOCUMENT_VERSIONS_DOCUMENT');
        $this->addSql('ALTER TABLE legal_document_versions DROP FOREIGN KEY FK_LEGAL_DOCUMENT_VERSIONS_CREATED_BY');
        $this->addSql('DROP TABLE legal_document_versions');
        $this->addSql('DROP TABLE legal_documents');
    }
}
