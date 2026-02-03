<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260203090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout latitude/longitude sur events';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD latitude DOUBLE DEFAULT NULL, ADD longitude DOUBLE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events DROP latitude, DROP longitude');
    }
}
