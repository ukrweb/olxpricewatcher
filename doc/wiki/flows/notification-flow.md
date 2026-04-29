# Notification Flow

Price change notifications are sent only to active subscribers.

Pending and unsubscribed subscriptions are ignored.

User-facing email subjects and bodies come from Symfony Translation domain `emails`. `LOCALE=ua` uses Ukrainian translations, `LOCALE=en` uses English translations, and unsupported locale values fall back to Ukrainian.

Unavailable listing notifications are also sent only to active subscribers. They are sent after repeated confirmed `not_found` responses, including HTTP `404` and `410`, and are not sent for timeouts, HTTP `5xx`/fetch failures, parser errors, or `no_price`. The notification threshold depends on `consecutive_not_found_count`, not `consecutive_fetch_error_count`.

The notification should include:
- listing title or URL
- old price
- new price
- currency

Unavailable listing emails include the listing title or URL and a calm note that the listing was not found after repeated checks.
