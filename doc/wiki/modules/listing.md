# Listing Module

The Listing module owns tracked OLX listing identity and current known state.

Responsibilities:
- normalized URL
- optional external ID
- current price and currency
- listing status
- check timestamps
- last error
- consecutive not_found count
- consecutive fetch error count
- unavailable notification timestamp

`listings.status` tracks OLX availability and is separate from `subscriptions.status`, which tracks email confirmation.

Successful price checks mark a listing `active`, reset availability counters, clear `last_error`, and clear `unavailable_notified_at` so a later disappearance can notify again.

HTTP `404` and `410` increment `consecutive_not_found_count` as confirmed unavailability. Fetch/network/parser failures increment `consecutive_fetch_error_count` as temporary failures. A page with no parsed price is `no_price`, not `not_found`, and does not trigger unavailable notifications.
