<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260125203852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stub: original base schema migration already applied.';
    }

    public function up(Schema $schema): void
    {
        // Intentionally empty (already applied).
    }

    public function down(Schema $schema): void
    {
        // Intentionally empty.
    }
}
