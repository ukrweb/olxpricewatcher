# Subscription Flow

1. Client sends URL and email to `POST /api/subscriptions`.
2. URL and email are validated.
3. URL is normalized.
4. Existing listing/subscription is checked by normalized URL and recipient email.
5. Duplicate active subscriptions are reused without resetting them to pending and without fetching OLX.
6. If the recipient address is inside `EMAIL_RATE_LIMIT_SECONDS`, an existing pending subscription is reused without changing its token; if no subscription exists, no new subscription is created. In both throttled cases, OLX is not fetched.
7. Current price is fetched from OLX only after throttling passes.
8. If the listing is not found, the price cannot be extracted, or fetching fails, the API returns a JSON error and saves nothing.
9. Listing is found or created by normalized URL and initialized with current price, currency, title, active status, and next check time.
10. Pending subscription is created or reused.
11. Confirmation token is generated or refreshed for pending subscriptions only when not throttled.
12. Confirmation email is sent with `{APP_BASE_URL}/api/subscriptions/confirm/{token}` when not throttled.
