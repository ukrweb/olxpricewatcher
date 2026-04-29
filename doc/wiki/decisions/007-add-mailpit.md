# Add Mailpit

## Decision

Docker Compose includes Mailpit for local email capture, and `.env.example` uses `smtp://mailpit:1025`.

## Consequences

Confirmation and price-change emails can be inspected at `http://localhost:8025` without sending real email. Real SMTP remains configurable through `MAILER_DSN`.
