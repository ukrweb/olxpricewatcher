# Invariants

1. One OLX listing is checked only once per cycle.
2. Multiple subscribers to the same listing must not create duplicate listing checks.
3. Pending subscriptions must not receive price-change notifications.
4. Email must be confirmed before subscription becomes active.
5. Price history is written only when price changes.
6. No notification is sent when price does not change.
7. Price fetching failures must not stop the whole worker run.
8. Documentation must stay synchronized with code.
9. Confirmation links must be absolute URLs built from `APP_BASE_URL`.
10. A confirmed token must not be reusable because only pending subscriptions are confirmable.
11. No Listing or Subscription may be created from `POST /api/subscriptions` if the submitted URL cannot be tracked.
12. Email validation must accept valid addresses such as `1subscriber@example.com`.
13. Pending subscriptions never cause a listing to be checked by the worker.
14. Unavailable notifications are sent only after repeated confirmed `not_found` responses, including HTTP `404` and `410`, not after transient network, HTTP `5xx`, or parser errors.
15. No price-change notification is sent to pending subscriptions.
16. Email confirmation is subscription-scoped.
