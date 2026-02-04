<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260204164500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow reports without event and store action + snapshot fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_3F3E4A58_71F7E88B');
        $this->addSql('ALTER TABLE reports CHANGE event_id event_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reports ADD event_title VARCHAR(255) DEFAULT NULL, ADD event_slug VARCHAR(255) DEFAULT NULL, ADD action_taken VARCHAR(20) DEFAULT NULL');
        $this->addSql('UPDATE reports r JOIN events e ON r.event_id = e.id SET r.event_title = e.title, r.event_slug = e.slug');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_3F3E4A58_71F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_3F3E4A58_71F7E88B');
        $this->addSql('ALTER TABLE reports DROP event_title, DROP event_slug, DROP action_taken');
        $this->addSql('ALTER TABLE reports CHANGE event_id event_id INT NOT NULL');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_3F3E4A58_71F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
    }
}
