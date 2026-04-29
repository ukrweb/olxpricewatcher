<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260429120185 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add last email sent timestamp for per-recipient email throttling.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions ADD last_email_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE subscriptions DROP last_email_sent_at');
    }
}
