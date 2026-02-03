<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add username to users with safe backfill before unique index.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE users SET username = CONCAT(SUBSTRING_INDEX(email, '@', 1), '-', id) WHERE username IS NULL OR username = ''");
        $this->addSql('ALTER TABLE users MODIFY username VARCHAR(60) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_username ON users (username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_username ON users');
        $this->addSql('ALTER TABLE users DROP username');
    }
}
