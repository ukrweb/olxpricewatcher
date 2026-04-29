# Email Confirmation Flow

1. User opens the full confirmation link from email.
2. Token is looked up on the `subscriptions` table.
3. If the subscription is already active, the endpoint returns `already_confirmed` without modifying it.
4. If the token belongs to a pending subscription, token expiration is checked.
5. A valid pending subscription becomes active.
6. Confirmed subscription can receive price-change notifications.
7. Unknown tokens return `404`; expired pending tokens return `410`.

Confirmation is per subscription, not per user account. The project does not have login, accounts, or authorization flows.
