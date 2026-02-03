<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add contact requests table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE contact_requests (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, name VARCHAR(120) NOT NULL, email VARCHAR(180) NOT NULL, subject VARCHAR(180) NOT NULL, message LONGTEXT NOT NULL, target_url VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, admin_response LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', responded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_99BA5C7EA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contact_requests ADD CONSTRAINT FK_99BA5C7EA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_requests DROP FOREIGN KEY FK_99BA5C7EA76ED395');
        $this->addSql('DROP TABLE contact_requests');
    }
}
