<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130212000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add place_images cache for Pexels photos.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE place_images (id INT AUTO_INCREMENT NOT NULL, place VARCHAR(180) NOT NULL, image_path VARCHAR(255) NOT NULL, photographer VARCHAR(180) DEFAULT NULL, pexels_url VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_place_image_place (place), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE place_images');
    }
}
