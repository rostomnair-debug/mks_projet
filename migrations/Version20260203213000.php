<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203213000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add websiteUrl to events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD website_url VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events DROP website_url');
    }
}
