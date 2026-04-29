<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428152026 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add listing availability counters and unavailable notification timestamp.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE listings ADD consecutive_not_found_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE listings ADD consecutive_fetch_error_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE listings ADD unavailable_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE listings DROP unavailable_notified_at');
        $this->addSql('ALTER TABLE listings DROP consecutive_fetch_error_count');
        $this->addSql('ALTER TABLE listings DROP consecutive_not_found_count');
    }
}
