<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426172524 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create listings, subscriptions, and price_history tables for OLX price watcher.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE listings (
            id SERIAL NOT NULL,
            source VARCHAR(32) DEFAULT 'olx' NOT NULL,
            external_id VARCHAR(255) DEFAULT NULL,
            original_url TEXT NOT NULL,
            normalized_url TEXT NOT NULL,
            title VARCHAR(500) DEFAULT NULL,
            current_price INT DEFAULT NULL,
            currency VARCHAR(16) DEFAULT NULL,
            status VARCHAR(32) NOT NULL,
            last_checked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            next_check_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_error TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE UNIQUE INDEX uniq_listing_normalized_url ON listings (normalized_url)');

        $this->addSql("CREATE TABLE subscriptions (
            id SERIAL NOT NULL,
            listing_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(32) NOT NULL,
            confirmation_token VARCHAR(128) NOT NULL,
            confirmation_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_notified_price INT DEFAULT NULL,
            last_notified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE INDEX idx_subscriptions_listing_id ON subscriptions (listing_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_subscription_listing_email ON subscriptions (listing_id, email)');
        $this->addSql('ALTER TABLE subscriptions ADD CONSTRAINT fk_subscriptions_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql("CREATE TABLE price_history (
            id SERIAL NOT NULL,
            listing_id INT NOT NULL,
            old_price INT DEFAULT NULL,
            new_price INT NOT NULL,
            currency VARCHAR(16) NOT NULL,
            source VARCHAR(64) NOT NULL,
            detected_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )");
        $this->addSql('CREATE INDEX idx_price_history_listing_id ON price_history (listing_id)');
        $this->addSql('ALTER TABLE price_history ADD CONSTRAINT fk_price_history_listing FOREIGN KEY (listing_id) REFERENCES listings (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE price_history DROP CONSTRAINT fk_price_history_listing');
        $this->addSql('ALTER TABLE subscriptions DROP CONSTRAINT fk_subscriptions_listing');
        $this->addSql('DROP TABLE price_history');
        $this->addSql('DROP TABLE subscriptions');
        $this->addSql('DROP TABLE listings');
    }
}
