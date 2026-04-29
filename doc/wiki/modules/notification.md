# Notification Module

The notification module sends emails.

Email types:
- confirmation email
- price changed email
- unavailable listing email

User-facing email text is plain text and localized through Symfony Translation.
Supported values are `ua` and `en`; unsupported values fall back to `ua`.

Email texts live in:
- `translations/emails.ua.yaml`
- `translations/emails.en.yaml`

The displayed site name in email text is injected from `PROJECT_NAME`.

`EmailFactory` creates Symfony `Email` objects and sets transport message fields. It delegates subject/body generation to `EmailTemplateRendererInterface`.

`SymfonyEmailTemplateRenderer` reads the Ukrainian and English plain-text templates from the `emails` translation domain for:
- confirmation email
- price changed email
- unavailable listing email

Symfony Mailer is used through `MAILER_DSN`.

Docker uses Mailpit by default, available at `http://localhost:8025`. Real SMTP can be configured by replacing `MAILER_DSN`.

Confirmation emails are throttled per recipient email address with `EMAIL_RATE_LIMIT_SECONDS`. If the address received another confirmation email too soon, the email is skipped before OLX is fetched; existing pending subscriptions keep their token, new subscriptions are not created, and the API returns `429 confirmation_throttled`.

Symfony mail transport failures are wrapped as `NotificationFailedException`. `POST /api/subscriptions` maps confirmation mail failures to HTTP `502` with `Unable to send confirmation email.` and does not expose raw SMTP/provider details.

Unavailable listing emails are sent only to active subscriptions after `OLX_UNAVAILABLE_NOTIFICATION_THRESHOLD` consecutive `not_found` checks. HTTP `404` and `410` count as `not_found`; HTTP `5xx`, timeouts, and parsing failures do not. Pending subscriptions do not receive these emails, and fetch-error counters do not trigger unavailable notifications.

Public subscription endpoints can be abused to send confirmation emails to third-party addresses. Production systems should add broader rate limiting, CAPTCHA, IP throttling, unsubscribe links, and sender reputation controls. For this test assignment, subscription-scoped email confirmation plus simple recipient-address throttling is sufficient.
