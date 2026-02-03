<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reports table for event reports';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reports (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, user_id INT DEFAULT NULL, reasons JSON NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_3F3E4A58_71F7E88B (event_id), INDEX IDX_3F3E4A58_A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_3F3E4A58_71F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_3F3E4A58_A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reports');
    }
}
