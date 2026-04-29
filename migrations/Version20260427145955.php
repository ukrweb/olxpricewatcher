<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427145955 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add subscription and listing indexes for confirmation, lookup, and worker scheduling.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE UNIQUE INDEX uniq_subscriptions_confirmation_token ON subscriptions (confirmation_token)',
        );
        $this->addSql('CREATE INDEX idx_subscriptions_email ON subscriptions (email)');
        $this->addSql('CREATE INDEX idx_subscriptions_status ON subscriptions (status)');
        $this->addSql('CREATE INDEX idx_listings_status ON listings (status)');
        $this->addSql('CREATE INDEX idx_listings_next_check_at ON listings (next_check_at)');
        $this->addSql('CREATE INDEX idx_listings_status_next_check_at ON listings (status, next_check_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_listings_status_next_check_at');
        $this->addSql('DROP INDEX idx_listings_next_check_at');
        $this->addSql('DROP INDEX idx_listings_status');
        $this->addSql('DROP INDEX idx_subscriptions_status');
        $this->addSql('DROP INDEX idx_subscriptions_email');
        $this->addSql('DROP INDEX uniq_subscriptions_confirmation_token');
    }
}
