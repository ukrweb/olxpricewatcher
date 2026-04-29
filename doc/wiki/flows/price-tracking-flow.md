# Price Tracking Flow

1. The automatic Docker worker runs `docker/worker/run.sh`, or a developer runs `docker compose exec app php bin/console app:check-prices` manually.
2. CLI command loads listings with at least one active subscription. Pending subscriptions do not make a listing eligible for worker checks.
3. Fetcher obtains current price.
4. Price is compared with listing current price.
5. If changed, history is saved and notifications are sent.
6. Listing status and timestamps are updated.
7. A failed listing is marked `not_found`, `no_price`, or `parse_error`, and the worker continues.
8. Consecutive `not_found` responses are counted separately from fetch/network/parser errors: HTTP `404` and `410` increment `consecutive_not_found_count`, while HTTP `5xx`, timeouts, and parser failures increment `consecutive_fetch_error_count`.
9. Active subscribers are notified that a listing is unavailable only after `OLX_UNAVAILABLE_NOTIFICATION_THRESHOLD` consecutive `not_found` checks and only once until the listing becomes available again.
10. The handler waits a small random delay between listing requests inside one worker cycle.

The worker waits a random number of seconds between `OLX_CHECK_INTERVAL_FROM_SECONDS` and `OLX_CHECK_INTERVAL_TO_SECONDS` after each full cycle. Invalid ranges fail fast in `docker/worker/run.sh`.

Listings created through `POST /api/subscriptions` already have an initial price. The worker performs later checks and writes price history only for changes after that baseline.
