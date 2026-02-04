<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204163000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin response fields to reports';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE reports ADD status VARCHAR(20) NOT NULL DEFAULT 'pending', ADD admin_response LONGTEXT DEFAULT NULL, ADD responded_at DATETIME DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reports DROP status, DROP admin_response, DROP responded_at');
    }
}
