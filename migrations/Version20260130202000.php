<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260130202000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event and quantity to contact requests.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_requests ADD event_id INT DEFAULT NULL, ADD requested_quantity INT NOT NULL DEFAULT 0');
        $this->addSql('CREATE INDEX IDX_99BA5C7E71F7E88B ON contact_requests (event_id)');
        $this->addSql('ALTER TABLE contact_requests ADD CONSTRAINT FK_99BA5C7E71F7E88B FOREIGN KEY (event_id) REFERENCES events (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE contact_requests DROP FOREIGN KEY FK_99BA5C7E71F7E88B');
        $this->addSql('DROP INDEX IDX_99BA5C7E71F7E88B ON contact_requests');
        $this->addSql('ALTER TABLE contact_requests DROP event_id, DROP requested_quantity');
    }
}
