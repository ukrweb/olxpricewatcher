# Data Model

## listings

Represents a unique OLX listing. It is the deduplication root.

Important fields:

- `original_url`
- `normalized_url`
- `external_id`
- `current_price`
- `currency`
- `status`
- `last_checked_at`
- `next_check_at`
- `last_error`
- `consecutive_not_found_count`
- `consecutive_fetch_error_count`
- `unavailable_notified_at`
- unique index on `normalized_url`
- index on `status`
- index on `next_check_at`
- composite index on `status, next_check_at` for worker scheduling

## subscriptions

Represents an email subscription to a listing.

Important fields:

- `listing_id`
- `email`
- `status`
- `confirmation_token`
- `confirmation_token_expires_at`
- `confirmed_at`
- `last_notified_price`
- `last_notified_at`
- `last_email_sent_at`

Constraint:

```text
unique(listing_id, email)
unique(confirmation_token)
```

Indexes:

```text
index(email)
index(status)
```

Subscription statuses are `pending`, `active`, and `unsubscribed`. They are separate from listing availability statuses.

Confirmation tokens remain on the subscription row. A token that belongs to an active subscription returns `already_confirmed`; unknown tokens return `404`, and expired pending tokens return `410`.

The email is intentionally stored directly on each subscription. The same email may appear on multiple rows when it subscribes to multiple listings.

`last_email_sent_at` records successful confirmation email sends. Repositories query the latest value across all subscriptions for the same email address to enforce `EMAIL_RATE_LIMIT_SECONDS` globally per recipient address.

## price_history

Stores actual price changes.

Important fields:

- `listing_id`
- `old_price`
- `new_price`
- `currency`
- `source`
- `detected_at`

Rows are written by the worker only when the fetched price differs from `listings.current_price`.

## Docker Connection

Inside Docker, application containers connect to PostgreSQL through `database:5432`. The exposed `POSTGRES_PORT` is for host tools only.
