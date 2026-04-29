# Subscription Module

The Subscription module owns email subscription state.

Responsibilities:
- email address
- confirmation token
- confirmation expiration
- subscription status
- last notified price/time
- last email sent time for confirmation throttling

`subscriptions.status` controls email confirmation: `pending`, `active`, or `unsubscribed`. It is separate from `listings.status`, which tracks OLX listing availability.

Creation rules:
- A subscription can be created only after the submitted OLX URL is normalized and an initial price is fetched.
- Pending duplicate subscriptions refresh their confirmation token and resend the confirmation email only when the recipient-address `EMAIL_RATE_LIMIT_SECONDS` throttle allows it.
- Pending duplicate subscriptions inside the email throttle window keep their existing token and return `429 confirmation_throttled`.
- New valid subscription requests for an address inside the throttle window return `429 confirmation_throttled` without creating a pending subscription.
- Active duplicate subscriptions are reused and are not reset to pending.
- Reusing an already confirmed token returns `already_confirmed` and does not modify the active subscription.
- Expired pending confirmation tokens return `410`.
- Email confirmation is scoped to a subscription. There is no user login/account system.
